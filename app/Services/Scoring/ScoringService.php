<?php

namespace App\Services\Scoring;

use App\Models\PersonalBest;
use App\Models\ScoringSession;
use App\Models\User;
use App\Services\GamificationService;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Core scoring business logic (Module 1 — TRACK). Handles offline-first,
 * idempotent persistence of sessions plus server-side recomputation of the
 * cached aggregates, personal-best detection, per-session analytics and the
 * progress dashboard.
 *
 * Conflict policy: last-write-wins per session (sessions are practically never
 * edited from two devices at once — see database-design.md §8).
 */
class ScoringService
{
    /**
     * Create or update a single session (with its ends/arrows) from a
     * normalized, validated payload. Idempotent via id + client_uuid.
     *
     * @param  array<string, mixed>  $data
     */
    public function persistSession(User $user, array $data): ScoringSession
    {
        return DB::transaction(function () use ($user, $data): ScoringSession {
            $session = $this->resolveSession($user, $data);

            $session->fill([
                'user_id' => $user->id,
                'equipment_profile_id' => $data['equipment_profile_id'] ?? $session->equipment_profile_id,
                'organization_id' => $data['organization_id'] ?? $session->organization_id,
                'scoring_session_group_id' => $data['scoring_session_group_id'] ?? $session->scoring_session_group_id,
                'title' => $data['title'] ?? $session->title,
                'bow_class' => $data['bow_class'] ?? $session->bow_class?->value,
                'distance_category' => $data['distance_category'] ?? $session->distance_category?->value,
                'distance_m' => $data['distance_m'] ?? $session->distance_m,
                'environment' => $data['environment'] ?? ($session->exists ? $session->environment->value : 'outdoor'),
                'target_face_cm' => $data['target_face_cm'] ?? $session->target_face_cm,
                'target_face_id' => $data['target_face_id'] ?? $session->target_face_id,
                'num_ends' => $data['num_ends'] ?? $session->num_ends,
                'arrows_per_end' => $data['arrows_per_end'] ?? $session->arrows_per_end,
                'status' => $data['status'] ?? ($session->exists ? $session->status->value : ScoringSessionStatus::InProgress->value),
                'notes' => $data['notes'] ?? $session->notes,
                'started_at' => $data['started_at'] ?? $session->started_at ?? now(),
                'completed_at' => $data['completed_at'] ?? $session->completed_at,
                'source' => $data['source'] ?? ($session->exists ? $session->source->value : 'mobile'),
                'client_uuid' => $data['client_uuid'] ?? $session->client_uuid,
            ]);

            // Preserve a client-generated ULID on first insert (offline-first).
            if (! $session->exists && ! empty($data['id'])) {
                $session->id = $data['id'];
            }

            $session->synced_at = now();
            $session->save();

            if (array_key_exists('ends', $data) && is_array($data['ends'])) {
                $this->replaceEnds($session, $data['ends']);
            }

            $this->recomputeAggregates($session);

            if ($session->status === ScoringSessionStatus::Completed) {
                $this->evaluatePersonalBest($session);

                try {
                    $gamification = app(GamificationService::class);
                    $gamification->recordSessionCompletion(
                        $user,
                        $session->arrows_shot,
                        (bool) $session->is_personal_best
                    );
                } catch (\Exception $e) {
                    \Log::error('Gamification reward error: '.$e->getMessage());
                }
            }

            $session->save();

            return $session->load('ends.arrows');
        });
    }

    /**
     * Idempotent batch sync of offline sessions. Returns canonical sessions.
     *
     * @param  array<int, array<string, mixed>>  $sessions
     * @return Collection<int, ScoringSession>
     */
    public function syncSessions(User $user, array $sessions): Collection
    {
        return collect($sessions)->map(
            fn (array $payload): ScoringSession => $this->persistSession($user, $payload)
        );
    }

    /**
     * Find an existing session for the user by id or client_uuid, else a new one.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveSession(User $user, array $data): ScoringSession
    {
        $query = ScoringSession::query()->where('user_id', $user->id);

        if (! empty($data['client_uuid'])) {
            $existing = (clone $query)->where('client_uuid', $data['client_uuid'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        if (! empty($data['id'])) {
            $existing = (clone $query)->whereKey($data['id'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return new ScoringSession;
    }

    /**
     * Replace all ends/arrows of a session (last-write-wins).
     *
     * @param  array<int, array<string, mixed>>  $ends
     */
    private function replaceEnds(ScoringSession $session, array $ends): void
    {
        $session->ends()->delete(); // cascades to arrows

        foreach ($ends as $endData) {
            $end = $session->ends()->create([
                'id' => $endData['id'] ?? null,
                'end_number' => $endData['end_number'],
                'end_total' => 0,
            ]);

            $arrows = $endData['arrows'] ?? [];
            $endTotal = 0;

            foreach ($arrows as $arrowData) {
                $isMiss = (bool) ($arrowData['is_miss'] ?? false);
                $scoreValue = $isMiss ? 0 : (int) ($arrowData['score_value'] ?? 0);

                $end->arrows()->create([
                    'id' => $arrowData['id'] ?? null,
                    'arrow_index' => $arrowData['arrow_index'],
                    'score_value' => $scoreValue,
                    'is_x' => (bool) ($arrowData['is_x'] ?? false),
                    'is_miss' => $isMiss,
                    'pos_x' => $arrowData['pos_x'] ?? null,
                    'pos_y' => $arrowData['pos_y'] ?? null,
                ]);

                $endTotal += $scoreValue;
            }

            $end->update(['end_total' => $endTotal]);
        }
    }

    /**
     * Recompute the cached aggregates on the session from its arrows.
     */
    public function recomputeAggregates(ScoringSession $session): void
    {
        $session->loadMissing('ends.arrows');

        $totalScore = 0;
        $arrowsShot = 0;
        $xCount = 0;
        $tenCount = 0;
        $missCount = 0;

        foreach ($session->ends as $end) {
            foreach ($end->arrows as $arrow) {
                $arrowsShot++;
                $totalScore += $arrow->score_value;
                if ($arrow->is_x) {
                    $xCount++;
                }
                if ($arrow->is_x || $arrow->score_value === 10) {
                    $tenCount++;
                }
                if ($arrow->is_miss) {
                    $missCount++;
                }
            }
        }

        $maxArrowValue = 10;
        if ($session->target_face_id) {
            $targetFace = \App\Models\TargetFace::find($session->target_face_id);
            if ($targetFace && !empty($targetFace->scoring_rules)) {
                $maxArrowValue = collect($targetFace->scoring_rules)->max('value') ?? 10;
            }
        }

        $session->total_score = $totalScore;
        $session->max_possible_score = $session->num_ends * $session->arrows_per_end * $maxArrowValue;
        $session->arrows_shot = $arrowsShot;
        $session->avg_per_arrow = $arrowsShot > 0 ? round($totalScore / $arrowsShot, 2) : null;
        $session->x_count = $xCount;
        $session->ten_count = $tenCount;
        $session->miss_count = $missCount;
    }

    /**
     * Detect and upsert the personal best for a completed session.
     * Sets is_personal_best on the session accordingly.
     */
    public function evaluatePersonalBest(ScoringSession $session): void
    {
        if ($session->arrows_shot <= 0) {
            $session->is_personal_best = false;

            return;
        }

        $pb = PersonalBest::query()->firstOrNew([
            'user_id' => $session->user_id,
            'bow_class' => $session->bow_class->value,
            'distance_category' => $session->distance_category->value,
            'num_arrows' => $session->arrows_shot,
        ]);

        $isNewBest = ! $pb->exists || $session->total_score > $pb->best_score;

        if ($isNewBest) {
            $pb->best_score = $session->total_score;
            $pb->scoring_session_id = $session->id;
            $pb->achieved_at = $session->completed_at ?? now();
            $pb->save();
        }

        $session->is_personal_best = $isNewBest;
    }

    /**
     * Per-session analytics (task 1.3): consistency, PB, comparison.
     *
     * @return array<string, mixed>
     */
    public function summary(ScoringSession $session): array
    {
        $session->loadMissing('ends.arrows');

        $endTotals = $session->ends->map(fn ($end): int => $end->end_total)->values()->all();
        $arrowValues = $session->ends
            ->flatMap(fn ($end) => $end->arrows->map(fn ($a): int => $a->score_value))
            ->values()->all();

        $previous = ScoringSession::query()
            ->where('user_id', $session->user_id)
            ->where('id', '!=', $session->id)
            ->where('bow_class', $session->bow_class->value)
            ->where('distance_category', $session->distance_category->value)
            ->where('status', ScoringSessionStatus::Completed->value)
            ->where('started_at', '<', $session->started_at)
            ->orderByDesc('started_at')
            ->first();

        return [
            'total_score' => $session->total_score,
            'max_possible_score' => $session->max_possible_score,
            'arrows_shot' => $session->arrows_shot,
            'avg_per_arrow' => $session->avg_per_arrow,
            'x_count' => $session->x_count,
            'ten_count' => $session->ten_count,
            'miss_count' => $session->miss_count,
            'is_personal_best' => $session->is_personal_best,
            'end_totals' => $endTotals,
            'best_end' => $endTotals === [] ? null : max($endTotals),
            'worst_end' => $endTotals === [] ? null : min($endTotals),
            // Grouping consistency: lower std-dev of arrow values = tighter. We
            // also expose a 0-100 index (10 = perfect, std 0 → 100).
            'consistency_std' => $this->standardDeviation($arrowValues),
            'consistency_index' => $this->consistencyIndex($arrowValues),
            'previous_comparison' => $previous === null ? null : [
                'session_id' => $previous->id,
                'avg_per_arrow' => $previous->avg_per_arrow,
                'total_score' => $previous->total_score,
                'avg_delta' => $session->avg_per_arrow !== null && $previous->avg_per_arrow !== null
                    ? round($session->avg_per_arrow - $previous->avg_per_arrow, 2)
                    : null,
            ],
        ];
    }

    /**
     * Progress dashboard aggregates for a user (task 1.9).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(User $user, array $filters = []): array
    {
        $base = ScoringSession::query()
            ->where('user_id', $user->id)
            ->where('status', ScoringSessionStatus::Completed->value);

        if (! empty($filters['bow_class'])) {
            $base->where('bow_class', $filters['bow_class']);
        }
        if (! empty($filters['distance_category'])) {
            $base->where('distance_category', $filters['distance_category']);
        }

        $sessions = (clone $base)->orderBy('started_at')->get();

        $totalSessions = $sessions->count();
        $totalArrows = (int) $sessions->sum('arrows_shot');
        $totalScore = (int) $sessions->sum('total_score');

        $now = Carbon::now();
        $weekAvg = $this->avgPerArrowSince($sessions, $now->copy()->subDays(7));
        $monthAvg = $this->avgPerArrowSince($sessions, $now->copy()->subDays(30));

        $trend = $sessions->map(fn (ScoringSession $s): array => [
            'session_id' => $s->id,
            'date' => $s->started_at->toDateString(),
            'avg_per_arrow' => $s->avg_per_arrow,
            'total_score' => $s->total_score,
        ])->values()->all();

        return [
            'total_sessions' => $totalSessions,
            'total_arrows' => $totalArrows,
            'total_score' => $totalScore,
            'overall_avg_per_arrow' => $totalArrows > 0 ? round($totalScore / $totalArrows, 2) : null,
            'week_avg_per_arrow' => $weekAvg,
            'month_avg_per_arrow' => $monthAvg,
            'current_streak_days' => $this->currentStreakDays($user),
            'personal_bests' => PersonalBest::query()
                ->where('user_id', $user->id)
                ->orderByDesc('best_score')
                ->limit(10)
                ->get()
                ->map(fn (PersonalBest $pb): array => [
                    'bow_class' => $pb->bow_class->value,
                    'distance_category' => $pb->distance_category->value,
                    'num_arrows' => $pb->num_arrows,
                    'best_score' => $pb->best_score,
                    'achieved_at' => $pb->achieved_at->toIso8601String(),
                ])->all(),
            'trend' => $trend,
        ];
    }

    /**
     * @param  Collection<int, ScoringSession>  $sessions
     */
    private function avgPerArrowSince(Collection $sessions, Carbon $since): ?float
    {
        $window = $sessions->filter(fn (ScoringSession $s): bool => $s->started_at->greaterThanOrEqualTo($since));
        $arrows = (int) $window->sum('arrows_shot');
        $score = (int) $window->sum('total_score');

        return $arrows > 0 ? round($score / $arrows, 2) : null;
    }

    /**
     * Consecutive days (ending today or yesterday) with at least one session.
     */
    private function currentStreakDays(User $user): int
    {
        $dates = ScoringSession::query()
            ->where('user_id', $user->id)
            ->whereNotNull('started_at')
            ->orderByDesc('started_at')
            ->pluck('started_at')
            ->map(fn (Carbon $d): string => $d->toDateString())
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $today = Carbon::now()->toDateString();
        $yesterday = Carbon::now()->subDay()->toDateString();

        if ($dates->first() !== $today && $dates->first() !== $yesterday) {
            return 0;
        }

        $streak = 0;
        $cursor = Carbon::parse($dates->first());

        foreach ($dates as $date) {
            if ($date === $cursor->toDateString()) {
                $streak++;
                $cursor->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * @param  array<int, int>  $values
     */
    private function standardDeviation(array $values): ?float
    {
        $n = count($values);
        if ($n === 0) {
            return null;
        }

        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn (int $v): float => ($v - $mean) ** 2, $values)) / $n;

        return round(sqrt($variance), 2);
    }

    /**
     * Map arrow-value std-dev to a 0-100 consistency index (std 0 → 100).
     *
     * @param  array<int, int>  $values
     */
    private function consistencyIndex(array $values): ?int
    {
        $std = $this->standardDeviation($values);
        if ($std === null) {
            return null;
        }

        // Max meaningful std for 0-10 scores ≈ 5. Clamp to [0, 100].
        $index = (int) round(max(0.0, 100.0 - ($std / 5.0 * 100.0)));

        return max(0, min(100, $index));
    }
}
