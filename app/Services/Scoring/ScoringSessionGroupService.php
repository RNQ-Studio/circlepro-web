<?php

namespace App\Services\Scoring;

use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Models\User;
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
     * can set it on a later tap and unlock PB.
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
                    $existing->save();
                }

                return $existing;
            }

            return $this->createParticipantRow($group, $user, [
                'id' => $data['id'] ?? null,
                'user_id' => $user->id,
                'participation_status' => ParticipationStatus::Self->value,
                'bow_class' => $data['bow_class'] ?? null,
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
        return DB::transaction(function () use ($session, $payload): ScoringSession {
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
                        || ($session->user_id !== null && $session->user_id === $actor->id);
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

        $entries = $this->assignRanks($rows, $group, $allCompleted);

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
            ],
        ];
    }

    /**
     * A cheap monotonic cursor for the leaderboard —
     * `{count}-{maxUpdatedMs}-{status}` over the group + its participants
     * (task 3.6 / Sprint 11 task 11.3). The count component catches a
     * participant being removed; max(updated_at) catches any score change; and
     * the trailing group status guarantees a lifecycle transition (finish /
     * abandon) always bumps the cursor — even when the host finishes the group
     * in the same millisecond as the last arrow, so the lifecycle-aware poll
     * (11.2) reliably learns it should stop. Equal version ⇒ nothing changed ⇒
     * the poll skips the heavy payload.
     */
    public function leaderboardVersion(ScoringSessionGroup $group): string
    {
        /** @var object{cnt: int, max_updated: string|null}|null $agg */
        $agg = $group->participants()
            ->selectRaw('count(*) as cnt, max(updated_at) as max_updated')
            ->first();

        $participantsMs = $agg?->max_updated !== null
            ? Carbon::parse($agg->max_updated)->getTimestampMs()
            : 0;
        $groupMs = $group->updated_at?->getTimestampMs() ?? 0;
        $count = (int) ($agg->cnt ?? 0);

        return $count.'-'.max($participantsMs, $groupMs).'-'.$group->status->value;
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

        $session->fill([
            'user_id' => $attributes['user_id'] ?? null,
            'guest_name' => $attributes['guest_name'] ?? null,
            'added_by_user_id' => $host->id,
            'organization_id' => $group->organization_id,
            'scoring_session_group_id' => $group->id,
            'participation_status' => $attributes['participation_status'],
            'bow_class' => $attributes['bow_class'] ?? null,
            'distance_category' => $attributes['distance_category'] ?? $group->distance_category->value,
            'distance_m' => $attributes['distance_m'] ?? $group->distance_m,
            'environment' => $group->environment->value,
            'target_face_cm' => $attributes['target_face_cm'] ?? $group->target_face_cm,
            'target_face_id' => $group->target_face_id,
            'target_butt' => $attributes['target_butt'] ?? null,
            'target_letter' => $attributes['target_letter'] ?? null,
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

    private function groupHasScores(ScoringSessionGroup $group): bool
    {
        return $group->participants()->where('arrows_shot', '>', 0)->exists();
    }
}
