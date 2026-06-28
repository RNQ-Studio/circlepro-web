<?php

namespace App\Services\Scoring;

use App\Models\GroupScorer;
use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\GroupScorerAssignmentType;
use App\Support\Enums\ParticipationStatus;
use App\Support\Enums\ScoringSessionStatus;
use App\Support\Enums\SyncSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle of a Latihan Bersama (group scoring) session — Sprint 02, Phase 0.
 *
 * Binder philosophy (§1): a group owns no scores of its own; every participant
 * is a real scoring_sessions row sharing the group's join_code and round
 * format. This service only manages the *binder* — creation, the join code,
 * batch quick-add of guests, removal and lifecycle transitions. Score input
 * and the leaderboard arrive in Sprint 03.
 */
class ScoringSessionGroupService
{
    /**
     * Anti-ambiguous alphabet for join codes: excludes O/0 and I/1 so a code
     * read aloud or off a poster can't be mistyped.
     */
    private const JOIN_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const JOIN_CODE_LENGTH = 6;

    public function __construct(private readonly ScoringService $scoring) {}

    /**
     * Create a group with a unique join_code; the caller becomes the host.
     * When "host ikut menembak" is on, the host also gets an owned participant
     * row (participation_status = self).
     *
     * @param  array<string, mixed>  $data
     */
    public function createGroup(User $host, array $data): ScoringSessionGroup
    {
        return DB::transaction(function () use ($host, $data): ScoringSessionGroup {
            $group = ScoringSessionGroup::query()->create([
                'host_user_id' => $host->id,
                'organization_id' => $data['organization_id'] ?? null,
                'title' => $data['title'] ?? null,
                'distance_category' => $data['distance_category'],
                'distance_m' => $data['distance_m'],
                'environment' => $data['environment'] ?? 'outdoor',
                'target_face_cm' => $data['target_face_cm'] ?? null,
                'target_face_id' => $data['target_face_id'] ?? null,
                'num_ends' => $data['num_ends'],
                'arrows_per_end' => $data['arrows_per_end'] ?? 6,
                'join_code' => $this->generateJoinCode(),
                'status' => ScoringSessionStatus::InProgress->value,
                'started_at' => $data['started_at'] ?? now(),
            ]);

            if (! empty($data['host_participates'])) {
                $this->createParticipantRow($group, $host, [
                    'user_id' => $host->id,
                    'participation_status' => ParticipationStatus::Self->value,
                    'bow_class' => $data['host_bow_class'] ?? null,
                ]);
            }

            return $group->loadCount('participants');
        });
    }

    /**
     * Batch quick-add of guests (K8) — many names in one call, metadata
     * optional. Idempotent: a re-sent row (same client_uuid, or same
     * client-generated id within the group) resolves to the existing
     * participant instead of duplicating it, so a double-tap is safe.
     *
     * @param  array<int, array<string, mixed>>  $participants
     * @return Collection<int, ScoringSession>
     */
    public function addParticipants(ScoringSessionGroup $group, User $host, array $participants): Collection
    {
        return DB::transaction(function () use ($group, $host, $participants): Collection {
            return collect($participants)->map(
                fn (array $participant): ScoringSession => $this->resolveExistingParticipant($group, $participant)
                    ?? $this->createParticipantRow($group, $host, [
                        'guest_name' => $participant['name'],
                        'participation_status' => ParticipationStatus::HostAdded->value,
                        'bow_class' => $participant['bow_class'] ?? null,
                        'distance_category' => $participant['distance_category'] ?? null,
                        'distance_m' => $participant['distance_m'] ?? null,
                        'target_face_cm' => $participant['target_face_cm'] ?? null,
                        'target_butt' => $participant['target_butt'] ?? null,
                        'target_letter' => $participant['target_letter'] ?? null,
                        'client_uuid' => $participant['client_uuid'] ?? null,
                        'id' => $participant['id'] ?? null,
                    ])
            )->values();
        });
    }

    /**
     * Self-join a group — Sprint 10, task 10.1. A "real" user joins for
     * themselves (K7): an owned row (user_id set, participation_status = self)
     * is minted, so consent is automatic — we never write to someone else's
     * stats. Idempotent (double-tap safe): one owned row per user, so a repeated
     * join resolves to the existing row instead of duplicating it. A bow class
     * may be supplied (optional, K8); it is also back-filled onto an existing
     * row that still lacks one, so a member who joined without picking a class
     * can set it on a later tap and unlock PB. The same back-fill applies to the
     * bantalan (target_butt/target_letter, Sprint 16 / task 16.1): a self-joiner
     * may map themselves at join time, and an unmapped row is filled on a later
     * tap — without ever overwriting a butt the host already assigned.
     *
     * @param  array<string, mixed>  $data
     */
    public function selfJoin(ScoringSessionGroup $group, User $user, array $data): ScoringSession
    {
        return DB::transaction(function () use ($group, $user, $data): ScoringSession {
            // Only a live session accepts joins; a finished/abandoned group is
            // closed (the leaderboard is already honest & final).
            abort_unless(
                $group->status === ScoringSessionStatus::InProgress,
                422,
                'Sesi sudah berakhir; tidak bisa bergabung.',
            );

            $existing = $group->participants()->where('user_id', $user->id)->first();
            if ($existing !== null) {
                if (! empty($data['bow_class']) && $existing->bow_class === null) {
                    $existing->bow_class = $data['bow_class'];
                }
                if (isset($data['target_butt']) && $existing->target_butt === null) {
                    $existing->target_butt = $data['target_butt'];
                    $existing->target_letter = $this->normalizeLetter($data['target_letter'] ?? null);
                }
                if ($this->mayUpdateParticipantDistance($existing) && array_key_exists('distance_m', $data)) {
                    $this->applyParticipantDistance($existing, $data);
                } elseif (array_key_exists('target_face_cm', $data) && $this->mayUpdateParticipantDistance($existing)) {
                    $this->applyParticipantDistance($existing, $data);
                }
                if ($existing->isDirty()) {
                    $existing->save();
                }

                return $existing;
            }

            return $this->createParticipantRow($group, $user, [
                'id' => $data['id'] ?? null,
                'user_id' => $user->id,
                'participation_status' => ParticipationStatus::Self->value,
                'bow_class' => $data['bow_class'] ?? null,
                'distance_m' => $data['distance_m'] ?? null,
                'target_face_cm' => $data['target_face_cm'] ?? null,
                'target_butt' => $data['target_butt'] ?? null,
                'target_letter' => $data['target_letter'] ?? null,
                'client_uuid' => $data['client_uuid'] ?? null,
            ]);
        });
    }

    /**
     * Remove a participant from the group (soft delete). The host may remove
     * anyone; a participant may remove only their own row (enforced by caller).
     */
    public function removeParticipant(ScoringSession $session): void
    {
        $session->delete();
    }

    /**
     * Set (or clear) a participant's bantalan — Sprint 16, task 16.2. The
     * bantalan (target_butt + optional target_letter) is the unit of parallel
     * work (§3.2 / Efisiensi E1); moving an archer between butts is pure roster
     * bookkeeping and never touches their score. Passing target_butt = null
     * un-maps the participant. Authorization (host or row-owner) is enforced by
     * the caller. The letter is normalized to uppercase for a stable contract.
     *
     * @param  array<string, mixed>  $data
     */
    public function assignParticipantButt(ScoringSession $session, array $data): ScoringSession
    {
        $butt = $data['target_butt'] ?? null;
        $session->target_butt = $butt;
        // A butt-less participant can't keep a letter (A/B/C/D is a seat ON a
        // butt); clearing the butt clears the letter too.
        $session->target_letter = $butt === null
            ? null
            : $this->normalizeLetter($data['target_letter'] ?? null);
        $session->save();

        return $session;
    }

    /**
     * Override a participant's real shooting distance/target face before any
     * score exists — Sprint 20. The group format is only the default; the row
     * carries the truth that feeds PB/statistics.
     *
     * @param  array<string, mixed>  $data
     */
    public function assignParticipantDistance(ScoringSession $session, array $data): ScoringSession
    {
        abort_unless(
            $this->mayUpdateParticipantDistance($session),
            422,
            'Jarak peserta tidak bisa diubah setelah skor masuk.',
        );

        $this->applyParticipantDistance($session, $data);
        $session->save();

        return $session;
    }

    /**
     * Host assigns a scorer to one bantalan. Re-assigning the same butt is a
     * deliberate host action, so it replaces the previous scorer.
     */
    public function assignScorer(
        ScoringSessionGroup $group,
        User $host,
        int $userId,
        int $targetButt,
    ): GroupScorer {
        return DB::transaction(function () use ($group, $host, $userId, $targetButt): GroupScorer {
            $scorer = GroupScorer::query()->updateOrCreate(
                [
                    'scoring_session_group_id' => $group->id,
                    'target_butt' => $targetButt,
                ],
                [
                    'user_id' => $userId,
                    'assigned_by_user_id' => $host->id,
                    'assignment_type' => GroupScorerAssignmentType::Assigned->value,
                ],
            );

            return $scorer->load(['user:id,name', 'assignedBy:id,name']);
        });
    }

    /**
     * A participant claims an unassigned bantalan. Claiming is intentionally
     * first-come, first-served; claim approval is not involved here, only score
     * ownership claims remain host-approved.
     */
    public function claimScorer(ScoringSessionGroup $group, User $user, int $targetButt): GroupScorer
    {
        return DB::transaction(function () use ($group, $user, $targetButt): GroupScorer {
            abort_if(
                $group->scorers()->where('target_butt', $targetButt)->exists(),
                422,
                'Bantalan ini sudah punya skorer.',
            );

            $scorer = $group->scorers()->create([
                'user_id' => $user->id,
                'assigned_by_user_id' => $user->id,
                'target_butt' => $targetButt,
                'assignment_type' => GroupScorerAssignmentType::Claimed->value,
            ]);

            return $scorer->load(['user:id,name', 'assignedBy:id,name']);
        });
    }

    /**
     * Round-robin auto-distribute participants across N bantalan — Sprint 16,
     * task 16.3. This is the throughput linchpin (Efisiensi E1): assigning 23
     * archers one-by-one eats the first whistle, so the host calls this once and
     * the field is mapped. The algorithm walks the roster in a stable order and
     * deals each archer onto the next butt (1..buttCount, wrapping), so the
     * counts stay within one of each other even when N isn't divisible by M.
     * Each "lap" around the butts advances the seat letter (A, B, C, …), so
     * every archer on a butt gets a distinct, fill-ordered letter.
     *
     * Capacity (seats per butt, default 4 = the usual A–D) caps the field: if
     * the roster needs more seats than buttCount × capacity, we refuse (422)
     * rather than silently overflow a butt. Host-only (enforced by the caller);
     * re-running it simply re-deals the whole field.
     *
     * @return Collection<int, ScoringSession>
     */
    public function autoDistributeButts(ScoringSessionGroup $group, int $buttCount, int $capacity): Collection
    {
        return DB::transaction(function () use ($group, $buttCount, $capacity): Collection {
            /** @var Collection<int, ScoringSession> $participants */
            $participants = $group->participants()
                ->with('user:id,name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $total = $participants->count();
            abort_if($total === 0, 422, 'Belum ada peserta untuk dibagi ke bantalan.');
            abort_if(
                $total > $buttCount * $capacity,
                422,
                "Terlalu banyak peserta ({$total}) untuk {$buttCount} bantalan × kapasitas {$capacity}.",
            );

            foreach ($participants->values() as $index => $participant) {
                $participant->target_butt = ($index % $buttCount) + 1;
                $participant->target_letter = $this->letterForSeat(intdiv($index, $buttCount));
                $participant->save();
            }

            return $participants;
        });
    }

    /**
     * Group the roster by bantalan — Sprint 16, task 16.4. The fondasi for the
     * per-bantalan UI (Sprint 18) and throughput monitor (Sprint 19): the flat
     * roster becomes buckets keyed by target_butt, each carrying its
     * participants and a small per-butt aggregate, plus a trailing bucket
     * (target_butt = null) for everyone still unmapped. Butts are returned in
     * ascending order; the unmapped bucket, if any, comes last.
     *
     * @return array{butts: array<int, array<string, mixed>>, meta: array{version: string, group_status: string, butt_count: int, mapped_count: int, unmapped_count: int, participant_count: int}}
     */
    public function rosterByButt(ScoringSessionGroup $group): array
    {
        $participants = $group->participants()
            ->with([
                'user:id,name',
                'ends' => fn ($q) => $q->withCount('arrows'),
            ])
            ->orderBy('target_letter')
            ->orderBy('created_at')
            ->get();
        $scorers = $group->scorers()
            ->with('user:id,name')
            ->get()
            ->keyBy('target_butt');

        $mapped = $participants->whereNotNull('target_butt');
        $unmapped = $participants->whereNull('target_butt')->values();

        $butts = $mapped
            ->groupBy('target_butt')
            ->sortKeys()
            ->map(fn (Collection $rows, int|string $butt): array => $this->buttBucket(
                (int) $butt,
                $rows,
                $scorers->get((int) $butt),
            ))
            ->values()
            ->all();

        if ($unmapped->isNotEmpty()) {
            $butts[] = $this->buttBucket(null, $unmapped, null);
        }

        $maxProgress = collect($butts)
            ->whereNotNull('target_butt')
            ->max('end_progress') ?? 0;
        foreach ($butts as &$bucket) {
            $bucket['lagging_by_ends'] = $bucket['target_butt'] === null
                ? 0
                : max(0, (int) $maxProgress - (int) $bucket['end_progress']);
            $bucket['is_lagging'] = $bucket['lagging_by_ends'] >= 2;
        }
        unset($bucket);

        return [
            'butts' => $butts,
            'meta' => [
                'version' => $this->leaderboardVersion($group),
                'group_status' => $group->status->value,
                'butt_count' => $mapped->pluck('target_butt')->unique()->count(),
                'mapped_count' => $mapped->count(),
                'unmapped_count' => $unmapped->count(),
                'participant_count' => $participants->count(),
            ],
        ];
    }

    /**
     * Update the group's title/round-format (only while no score exists) or
     * transition its lifecycle (finish/abandon). Round-format edits after a
     * score is in would make the leaderboard dishonest, so they are blocked.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateGroup(ScoringSessionGroup $group, array $data): ScoringSessionGroup
    {
        return DB::transaction(function () use ($group, $data): ScoringSessionGroup {
            $formatKeys = [
                'distance_category', 'distance_m', 'environment',
                'target_face_cm', 'target_face_id', 'num_ends', 'arrows_per_end',
            ];

            $changingFormat = array_intersect_key($data, array_flip($formatKeys)) !== [];
            if ($changingFormat && $this->groupHasScores($group)) {
                abort(422, 'Format ronde tidak bisa diubah setelah ada skor masuk.');
            }

            if (array_key_exists('status', $data)) {
                $group->status = $data['status'];
                $group->completed_at = match ($data['status']) {
                    ScoringSessionStatus::Completed->value,
                    ScoringSessionStatus::Abandoned->value => $group->completed_at ?? now(),
                    default => null,
                };
            }

            foreach (['title', ...$formatKeys] as $key) {
                if (array_key_exists($key, $data)) {
                    $group->{$key} = $data[$key];
                }
            }

            $group->save();

            return $group->loadCount('participants');
        });
    }

    /**
     * Persist one participant's score (ends/arrows) into an existing roster row
     * via the shared scoring pipeline — Sprint 03, task 3.1. Idempotent &
     * last-write-wins: re-sending the same ends replaces them, so a retry is
     * safe (task 3.2). Authorization (host or row-owner) is enforced by the
     * caller. Binder integrity (§3.2 / task 3.7): only an OWNED row
     * (user_id != NULL) ever touches personal-best/gamification — a guest
     * scored by the host never feeds anyone's stats.
     *
     * @param  array<string, mixed>  $payload
     */
    public function persistParticipantScore(
        ScoringSessionGroup $group,
        ScoringSession $session,
        User $actor,
        array $payload,
    ): ScoringSession {
        return DB::transaction(function () use ($session, $actor, $payload): ScoringSession {
            // Keep the client's idempotency key on the row once it arrives.
            if (! empty($payload['client_uuid']) && $session->client_uuid === null) {
                $session->client_uuid = $payload['client_uuid'];
            }

            if (! empty($payload['status'])) {
                $session->status = $payload['status'];
                $session->completed_at = match ($payload['status']) {
                    ScoringSessionStatus::Completed->value,
                    ScoringSessionStatus::Abandoned->value => $payload['completed_at']
                        ?? $session->completed_at ?? now(),
                    default => null,
                };
            } elseif (array_key_exists('completed_at', $payload)) {
                $session->completed_at = $payload['completed_at'];
            }

            $session->synced_at = now();
            $session->last_scored_by_user_id = $actor->id;
            $session->save();

            // Delegate ends/arrows + aggregate recompute to the existing pipeline.
            if (array_key_exists('ends', $payload) && is_array($payload['ends'])) {
                $this->scoring->replaceParticipantEnds($session, $payload['ends']);
                $session->save();
            }

            // Guests (user_id NULL) are walled off from PB/gamification (§3.2).
            // bow_class may be NULL for an owned guest-turned-self before claim,
            // so guard PB on it to avoid a NPE in evaluatePersonalBest().
            if ($session->user_id !== null && $session->status === ScoringSessionStatus::Completed) {
                if ($session->bow_class !== null && $session->distance_category !== null) {
                    $this->scoring->evaluatePersonalBest($session);
                }
                $this->scoring->awardSessionGamification($session);
                $session->save();
            }

            return $session->load('ends.arrows');
        });
    }

    /**
     * Idempotent batch sync of participant scores for one group — Sprint 03,
     * task 3.3. Forgives a flaky connection: each row is resolved by its
     * client-generated id or client_uuid (no duplicates on retry) then scored.
     * A row the client created offline that the server hasn't seen yet is
     * created on the fly — but only the host may mint new participants in
     * Phase 0 (matrix §4). A non-host may only sync their own owned row.
     *
     * @param  array<int, array<string, mixed>>  $sessions
     * @return Collection<int, ScoringSession>
     */
    public function syncParticipantScores(ScoringSessionGroup $group, User $actor, array $sessions): Collection
    {
        return DB::transaction(function () use ($group, $actor, $sessions): Collection {
            $isHost = $group->host_user_id === $actor->id;

            return collect($sessions)->map(function (array $item) use ($group, $actor, $isHost): ScoringSession {
                $session = $this->resolveExistingParticipant($group, $item);

                if ($session === null) {
                    abort_unless($isHost, 403, 'Hanya host yang bisa menambah peserta lewat sync.');
                    $session = $this->createParticipantRow($group, $actor, [
                        'guest_name' => $item['name'] ?? null,
                        'participation_status' => ParticipationStatus::HostAdded->value,
                        'bow_class' => $item['bow_class'] ?? null,
                        'distance_category' => $item['distance_category'] ?? null,
                        'distance_m' => $item['distance_m'] ?? null,
                        'target_face_cm' => $item['target_face_cm'] ?? null,
                        'target_butt' => $item['target_butt'] ?? null,
                        'target_letter' => $item['target_letter'] ?? null,
                        'client_uuid' => $item['client_uuid'] ?? null,
                        'id' => $item['id'] ?? null,
                    ]);
                } else {
                    $canWrite = $isHost
                        || ($session->user_id !== null && $session->user_id === $actor->id)
                        || $this->actorIsScorerForSession($group, $actor, $session);
                    abort_unless($canWrite, 403, 'Tidak diizinkan menulis skor peserta ini.');
                }

                return $this->persistParticipantScore($group, $session, $actor, $item);
            })->values();
        });
    }

    /**
     * Fair aggregate leaderboard for a group — Sprint 03, tasks 3.4–3.6.
     *
     * Honesty (K3 "hormati peluit"): while the round is live we rank on the
     * SAME number of *validated* rounds across racers (comparable_total), so a
     * shooter who merely fired more arrows can't lead on raw total; once every
     * racer is `completed` the absolute total is the honest measure. Tie-break
     * is `total → x_count → ten_count → SERI` (K4) — no completed_at, no
     * arrows_shot. The returned `meta.version` is a cheap cursor for
     * lifecycle-aware polling (Phase 1).
     *
     * @return array{entries: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function leaderboard(ScoringSessionGroup $group): array
    {
        $participants = $group->participants()
            ->with(['user:id,name', 'ends' => fn ($q) => $q->withCount('arrows')])
            ->get();

        $rows = $participants->map(fn (ScoringSession $p): array => $this->buildLeaderboardRow($p))->all();
        $distanceGroups = collect($rows)
            ->groupBy('distance_key')
            ->sortBy(fn (Collection $groupRows): array => [
                (int) ($groupRows->first()['distance_m'] ?? 0),
                (int) ($groupRows->first()['target_face_cm'] ?? 0),
            ]);

        $entries = [];
        $distanceMeta = [];
        $comparableEndsMeta = [];
        $allCompletedMeta = [];

        foreach ($distanceGroups as $groupRows) {
            $ranked = $this->rankLeaderboardRows($groupRows->values()->all(), $group);
            $distanceEntries = $ranked['entries'];
            $distanceMeta[] = [
                'distance_key' => $groupRows->first()['distance_key'],
                'distance_label' => $groupRows->first()['distance_label'],
                'distance_m' => $groupRows->first()['distance_m'],
                'target_face_cm' => $groupRows->first()['target_face_cm'],
                'participant_count' => count($distanceEntries),
                'all_completed' => $ranked['all_completed'],
                'comparable_ends' => $ranked['comparable_ends'],
            ];
            $comparableEndsMeta[] = $ranked['comparable_ends'];
            $allCompletedMeta[] = $ranked['all_completed'];

            foreach ($distanceEntries as $entry) {
                $entry['distance_group_size'] = count($distanceEntries);
                $entries[] = $entry;
            }
        }

        $allCompleted = $allCompletedMeta !== [] && ! in_array(false, $allCompletedMeta, true);
        $comparableEnds = $comparableEndsMeta === [] ? 0 : min($comparableEndsMeta);

        return [
            'entries' => $entries,
            'meta' => [
                'version' => $this->leaderboardVersion($group),
                // The group lifecycle drives the client's lifecycle-aware poll
                // (Sprint 11, task 11.2): once it leaves `in_progress` the live
                // screen stops polling — even when another device finished it.
                'group_status' => $group->status->value,
                'all_completed' => $allCompleted,
                'is_provisional' => ! $allCompleted,
                'comparable_ends' => $comparableEnds,
                'target_ends' => $group->num_ends,
                'participant_count' => count($rows),
                'distance_groups' => $distanceMeta,
            ],
        ];
    }

    /**
     * A cheap monotonic cursor for the leaderboard —
     * `{participantCount}:{scorerCount}:{scorerSig}-{maxUpdatedMs}-{status}`
     * over the group, participants and scorer assignments (task 3.6 / Sprint
     * 11 task 11.3, expanded in Sprint 19). Counts catch removal,
     * max(updated_at) catches score changes, the scorer signature catches a
     * same-millisecond scorer reassignment, and the trailing group status
     * guarantees lifecycle transitions. Equal version ⇒ nothing changed ⇒ the
     * poll skips the heavy payload.
     */
    public function leaderboardVersion(ScoringSessionGroup $group): string
    {
        /** @var object{cnt: int, max_updated: string|null}|null $agg */
        $agg = $group->participants()
            ->selectRaw('count(*) as cnt, max(updated_at) as max_updated')
            ->first();
        $scorers = $group->scorers()
            ->orderBy('target_butt')
            ->get(['target_butt', 'user_id', 'updated_at']);

        $participantsMs = $agg?->max_updated !== null
            ? Carbon::parse($agg->max_updated)->getTimestampMs()
            : 0;
        $scorersMs = $scorers
            ->map(fn (GroupScorer $scorer): int => $scorer->updated_at?->getTimestampMs() ?? 0)
            ->max() ?? 0;
        $groupMs = $group->updated_at?->getTimestampMs() ?? 0;
        $count = (int) ($agg->cnt ?? 0);
        $scorerCount = $scorers->count();
        $scorerSignature = sha1($scorers
            ->map(fn (GroupScorer $scorer): string => $scorer->target_butt.':'.$scorer->user_id)
            ->implode('|'));

        return $count.':'.$scorerCount.':'.$scorerSignature.'-'
            .max($participantsMs, $scorersMs, $groupMs).'-'.$group->status->value;
    }

    /**
     * Flatten one participant into the raw figures the leaderboard ranks on.
     * A "validated" round is an end whose recorded arrow count equals the row's
     * arrows_per_end — i.e. a round actually shot to completion, not a partial
     * end still in progress.
     *
     * @return array<string, mixed>
     */
    private function buildLeaderboardRow(ScoringSession $p): array
    {
        $validatedEndTotals = [];
        foreach ($p->ends as $end) {
            // arrows_count is supplied by withCount('arrows') on the eager load.
            if ((int) $end->arrows_count === $p->arrows_per_end) {
                $validatedEndTotals[] = (int) $end->end_total;
            }
        }

        return [
            'session_id' => $p->id,
            'user_id' => $p->user_id,
            'is_guest' => $p->isGuest(),
            'display_name' => $p->guest_name ?? $p->user?->name,
            'bow_class' => $p->bow_class?->value,
            'distance_m' => $p->distance_m,
            'target_face_cm' => $p->target_face_cm,
            'distance_key' => $this->distanceKey($p->distance_m, $p->target_face_cm),
            'distance_label' => $this->distanceLabel($p->distance_m, $p->target_face_cm),
            'status' => $p->status?->value,
            'total_score' => (int) $p->total_score,
            'x_count' => (int) $p->x_count,
            'ten_count' => (int) $p->ten_count,
            'arrows_shot' => (int) $p->arrows_shot,
            'validated_ends' => count($validatedEndTotals),
            'validated_end_totals' => $validatedEndTotals,
        ];
    }

    /**
     * Rank one fair comparison group. Sprint 20 uses this per distance/face so
     * a 15m beginner never outranks or trails a 50m archer on the same scale.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{entries: array<int, array<string, mixed>>, all_completed: bool, comparable_ends: int}
     */
    private function rankLeaderboardRows(array $rows, ScoringSessionGroup $group): array
    {
        // A "racer" is anyone still in the running (not abandoned). A round is
        // provisional until every racer has finished.
        $racers = array_values(array_filter(
            $rows,
            fn (array $r): bool => $r['status'] !== ScoringSessionStatus::Abandoned->value,
        ));
        $started = array_filter($racers, fn (array $r): bool => $r['validated_ends'] > 0);

        $comparableEnds = $started === []
            ? 0
            : (int) min(array_map(fn (array $r): int => $r['validated_ends'], $started));

        $allCompleted = $racers !== [] && array_filter(
            $racers,
            fn (array $r): bool => $r['status'] !== ScoringSessionStatus::Completed->value,
        ) === [];

        foreach ($rows as &$row) {
            $row['comparable_total'] = (int) array_sum(array_slice($row['validated_end_totals'], 0, $comparableEnds));
            $row['rank_metric'] = $allCompleted ? $row['total_score'] : $row['comparable_total'];
        }
        unset($row);

        // Tie-break key (K4): metric → x → ten. Equal on all three ⇒ SERI.
        usort($rows, static fn (array $a, array $b): int => [$b['rank_metric'], $b['x_count'], $b['ten_count']]
            <=> [$a['rank_metric'], $a['x_count'], $a['ten_count']]);

        return [
            'entries' => $this->assignRanks($rows, $group, $allCompleted),
            'all_completed' => $allCompleted,
            'comparable_ends' => $comparableEnds,
        ];
    }

    /**
     * Turn the sorted rows into presented entries with a rank. Rows equal on
     * the full tie-break key share a rank (SERI) and are flagged `tied`.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function assignRanks(array $rows, ScoringSessionGroup $group, bool $allCompleted): array
    {
        $entries = [];
        $previousKey = null;
        $previousRank = 0;

        foreach ($rows as $index => $row) {
            $key = [$row['rank_metric'], $row['x_count'], $row['ten_count']];
            $rank = ($previousKey !== null && $key === $previousKey) ? $previousRank : $index + 1;
            $previousKey = $key;
            $previousRank = $rank;

            $entries[] = [
                'rank' => $rank,
                'session_id' => $row['session_id'],
                'user_id' => $row['user_id'],
                'is_guest' => $row['is_guest'],
                'display_name' => $row['display_name'],
                'bow_class' => $row['bow_class'],
                'distance_m' => $row['distance_m'],
                'target_face_cm' => $row['target_face_cm'],
                'distance_key' => $row['distance_key'],
                'distance_label' => $row['distance_label'],
                'status' => $row['status'],
                'total_score' => $row['total_score'],
                'x_count' => $row['x_count'],
                'ten_count' => $row['ten_count'],
                'arrows_shot' => $row['arrows_shot'],
                'validated_ends' => $row['validated_ends'],
                'target_ends' => $group->num_ends,
                // Honest live figure: total on the common number of rounds (K3).
                'comparable_total' => $row['comparable_total'],
                'is_complete' => $row['status'] === ScoringSessionStatus::Completed->value,
            ];
        }

        // Second pass: a rank shared by >1 entry is a SERI; the UI may label a
        // sole rank-1 entry "memimpin sementara · N/M" only while provisional.
        $rankCounts = array_count_values(array_map(static fn (array $e): int => $e['rank'], $entries));
        foreach ($entries as &$entry) {
            $entry['tied'] = $rankCounts[$entry['rank']] > 1;
            $entry['is_provisional_leader'] = ! $allCompleted && $entry['rank'] === 1 && ! $entry['tied'];
        }
        unset($entry);

        return $entries;
    }

    /**
     * Generate a unique anti-ambiguous join code, retrying on collision.
     * Collision check includes trashed groups so the DB unique index never
     * trips on a soft-deleted code.
     */
    public function generateJoinCode(): string
    {
        $max = strlen(self::JOIN_CODE_ALPHABET) - 1;

        do {
            $code = '';
            for ($i = 0; $i < self::JOIN_CODE_LENGTH; $i++) {
                $code .= self::JOIN_CODE_ALPHABET[random_int(0, $max)];
            }
        } while (ScoringSessionGroup::withTrashed()->where('join_code', $code)->exists());

        return $code;
    }

    /**
     * Resolve an already-stored participant for an idempotent re-send.
     *
     * @param  array<string, mixed>  $participant
     */
    private function resolveExistingParticipant(ScoringSessionGroup $group, array $participant): ?ScoringSession
    {
        if (! empty($participant['client_uuid'])) {
            $existing = $group->participants()
                ->where('client_uuid', $participant['client_uuid'])
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        if (! empty($participant['id'])) {
            return $group->participants()->whereKey($participant['id'])->first();
        }

        return null;
    }

    /**
     * Create one participant scoring_sessions row, inheriting the group's round
     * format for anything the caller left unset (K8: metadata optional).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createParticipantRow(ScoringSessionGroup $group, User $host, array $attributes): ScoringSession
    {
        $session = new ScoringSession;

        if (! empty($attributes['id'])) {
            $session->id = $attributes['id'];
        }

        $distance = $this->participantDistanceAttributes($attributes, $group);

        $session->fill([
            'user_id' => $attributes['user_id'] ?? null,
            'guest_name' => $attributes['guest_name'] ?? null,
            'added_by_user_id' => $host->id,
            'organization_id' => $group->organization_id,
            'scoring_session_group_id' => $group->id,
            'participation_status' => $attributes['participation_status'],
            'bow_class' => $attributes['bow_class'] ?? null,
            'distance_category' => $distance['distance_category'],
            'distance_m' => $distance['distance_m'],
            'environment' => $group->environment->value,
            'target_face_cm' => $distance['target_face_cm'],
            'target_face_id' => $group->target_face_id,
            'target_butt' => $attributes['target_butt'] ?? null,
            'target_letter' => $this->normalizeLetter($attributes['target_letter'] ?? null),
            'num_ends' => $group->num_ends,
            'arrows_per_end' => $group->arrows_per_end,
            'status' => ScoringSessionStatus::InProgress->value,
            'started_at' => $group->started_at,
            'client_uuid' => $attributes['client_uuid'] ?? null,
            'source' => SyncSource::Mobile->value,
        ]);

        $session->save();

        return $session;
    }

    private function actorIsScorerForSession(ScoringSessionGroup $group, User $actor, ScoringSession $session): bool
    {
        return $session->target_butt !== null
            && $group->scorers()
                ->where('user_id', $actor->id)
                ->where('target_butt', $session->target_butt)
                ->exists();
    }

    private function mayUpdateParticipantDistance(ScoringSession $session): bool
    {
        return (int) $session->arrows_shot === 0 && ! $session->ends()->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyParticipantDistance(ScoringSession $session, array $data): void
    {
        if (array_key_exists('distance_m', $data) && $data['distance_m'] !== null) {
            $distance = (int) $data['distance_m'];
            $session->distance_m = $distance;
            $session->distance_category = DistanceCategory::from($this->distanceCategoryForMeters($distance));
        }

        if (array_key_exists('target_face_cm', $data)) {
            $session->target_face_cm = $data['target_face_cm'];
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{distance_category: string, distance_m: int, target_face_cm: int|null}
     */
    private function participantDistanceAttributes(array $attributes, ScoringSessionGroup $group): array
    {
        $distance = (int) ($attributes['distance_m'] ?? $group->distance_m);
        $category = $attributes['distance_category']
            ?? (array_key_exists('distance_m', $attributes) && $attributes['distance_m'] !== null
                ? $this->distanceCategoryForMeters($distance)
                : $group->distance_category->value);

        return [
            'distance_category' => $category,
            'distance_m' => $distance,
            'target_face_cm' => $attributes['target_face_cm'] ?? $group->target_face_cm,
        ];
    }

    private function distanceCategoryForMeters(int $meters): string
    {
        $value = "{$meters}m";
        foreach (DistanceCategory::cases() as $case) {
            if ($case->value === $value) {
                return $value;
            }
        }

        abort(422, "Jarak {$meters}m belum didukung sebagai kategori statistik.");
    }

    private function distanceKey(int $distanceM, ?int $targetFaceCm): string
    {
        return $distanceM.'m|'.($targetFaceCm ?? 'face-default');
    }

    private function distanceLabel(int $distanceM, ?int $targetFaceCm): string
    {
        return $targetFaceCm === null
            ? "{$distanceM}m"
            : "{$distanceM}m / {$targetFaceCm}cm";
    }

    private function groupHasScores(ScoringSessionGroup $group): bool
    {
        return $group->participants()->where('arrows_shot', '>', 0)->exists();
    }

    /**
     * Build one per-bantalan bucket (task 16.4): the participant models plus a
     * small aggregate the throughput monitor (Sprint 19) will lean on. The
     * `target_butt` is null for the trailing "unmapped" bucket.
     *
     * @param  Collection<int, ScoringSession>  $rows
     * @return array<string, mixed>
     */
    private function buttBucket(?int $butt, Collection $rows, ?GroupScorer $scorer): array
    {
        $progress = $rows->map(fn (ScoringSession $row): int => $this->validatedEndCount($row));
        $endProgress = (int) ($progress->min() ?? 0);
        $maxEndProgress = (int) ($progress->max() ?? 0);
        $participantCount = $rows->count();
        $completedCount = $rows
            ->where('status', ScoringSessionStatus::Completed)
            ->count();
        $isComplete = $participantCount > 0 && $completedCount === $participantCount;
        $targetEnds = (int) $rows->first()->num_ends;
        $submittedCount = $isComplete
            ? $participantCount
            : $rows
                ->filter(fn (ScoringSession $row): bool => $this->validatedEndCount($row) > $endProgress)
                ->count();

        return [
            'target_butt' => $butt,
            'participant_count' => $participantCount,
            'completed_count' => $completedCount,
            'submitted_count' => $submittedCount,
            'pending_count' => max(0, $participantCount - $submittedCount),
            'end_progress' => $endProgress,
            'max_end_progress' => $maxEndProgress,
            'current_end' => $completedCount === $participantCount && $participantCount > 0
                ? null
                : min($endProgress + 1, $targetEnds),
            'target_ends' => $targetEnds,
            'is_complete' => $isComplete,
            'total_score' => (int) $rows->sum('total_score'),
            'scorer' => $scorer === null ? null : [
                'id' => $scorer->id,
                'user_id' => $scorer->user_id,
                'target_butt' => $scorer->target_butt,
                'assignment_type' => $scorer->assignment_type->value,
                'scorer' => $scorer->user === null ? null : [
                    'id' => $scorer->user->id,
                    'name' => $scorer->user->name,
                ],
            ],
            'participants' => $rows->values(),
        ];
    }

    private function validatedEndCount(ScoringSession $session): int
    {
        return $session->ends
            ->filter(fn ($end): bool => (int) $end->arrows_count === $session->arrows_per_end)
            ->count();
    }

    /**
     * The seat letter for the n-th lap of the round-robin: 0 → A, 1 → B, ….
     * The capacity cap (≤ 26, enforced by the request) keeps the lap within A–Z,
     * so the single-char (char(1)) contract always holds.
     */
    private function letterForSeat(int $lap): string
    {
        return chr(ord('A') + min($lap, 25));
    }

    /**
     * Normalize a target letter to a single uppercase char for a stable
     * contract; blank/null stays null (an unmapped seat).
     */
    private function normalizeLetter(?string $letter): ?string
    {
        if ($letter === null || trim($letter) === '') {
            return null;
        }

        return strtoupper(substr(trim($letter), 0, 1));
    }
}
