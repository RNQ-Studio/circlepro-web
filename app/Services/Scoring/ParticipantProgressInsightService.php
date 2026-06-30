<?php

namespace App\Services\Scoring;

use App\Models\ScoringSession;
use App\Support\Enums\ScoringSessionStatus;

/**
 * Builds lightweight per-participant feedback for Latihan Bersama boards.
 *
 * The baseline query deliberately stays comparable: same user, bow class,
 * distance, target face and counted arrow count. Guests still get end trend,
 * but never a personal baseline.
 */
class ParticipantProgressInsightService
{
    /**
     * @return array<string, mixed>
     */
    public function forSession(ScoringSession $session): array
    {
        $trend = $this->endTrend($session);
        $baseline = $this->baseline($session);

        return [
            'baseline' => $baseline,
            'end_trend' => $trend,
            'callout' => $this->callout($baseline),
        ];
    }

    /**
     * @return array<int, array{end_number: int, total: int, is_sighter: bool}>
     */
    private function endTrend(ScoringSession $session): array
    {
        $session->loadMissing('ends.arrows');

        return $session->ends
            ->sortBy('end_number')
            ->map(fn ($end): array => [
                'end_number' => (int) $end->end_number,
                'total' => (int) $end->end_total,
                'is_sighter' => (bool) $end->is_sighter,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseline(ScoringSession $session): array
    {
        $empty = [
            'has_baseline' => false,
            'sessions_count' => 0,
            'average_score' => null,
            'best_score' => null,
            'delta_vs_average' => null,
            'delta_vs_best' => null,
            'label' => $session->user_id === null
                ? 'Tamu: klaim skor untuk insight pribadi'
                : 'Baseline pertama di format ini',
        ];

        if (
            $session->user_id === null
            || $session->bow_class === null
            || $session->distance_category === null
            || (int) $session->arrows_shot <= 0
        ) {
            return $empty;
        }

        $query = ScoringSession::query()
            ->where('user_id', $session->user_id)
            ->where('id', '!=', $session->id)
            ->where('status', ScoringSessionStatus::Completed->value)
            ->where('bow_class', $session->bow_class->value)
            ->where('distance_category', $session->distance_category->value)
            ->where('distance_m', $session->distance_m)
            ->where('arrows_shot', $session->arrows_shot);

        if ($session->target_face_cm === null) {
            $query->whereNull('target_face_cm');
        } else {
            $query->where('target_face_cm', $session->target_face_cm);
        }

        if ($session->started_at !== null) {
            $query->where('started_at', '<', $session->started_at);
        }

        $sessionsCount = (clone $query)->count();
        if ($sessionsCount === 0) {
            return $empty;
        }

        $averageScore = round((float) (clone $query)->avg('total_score'), 1);
        $bestScore = (int) (clone $query)->max('total_score');
        $deltaVsAverage = round((float) $session->total_score - $averageScore, 1);
        $deltaVsBest = (int) $session->total_score - $bestScore;

        return [
            'has_baseline' => true,
            'sessions_count' => $sessionsCount,
            'average_score' => $averageScore,
            'best_score' => $bestScore,
            'delta_vs_average' => $deltaVsAverage,
            'delta_vs_best' => $deltaVsBest,
            'label' => $this->baselineLabel($deltaVsAverage, $deltaVsBest),
        ];
    }

    /**
     * @param  array<string, mixed>  $baseline
     */
    private function callout(array $baseline): string
    {
        if (! ($baseline['has_baseline'] ?? false)) {
            return (string) $baseline['label'];
        }

        if (($baseline['delta_vs_best'] ?? 0) > 0) {
            return 'Rekor format baru dari baseline sebelumnya.';
        }

        if (($baseline['delta_vs_average'] ?? 0) > 0) {
            return 'Lebih baik dari rata-rata format ini.';
        }

        return 'Baseline pembanding tersimpan untuk sesi berikutnya.';
    }

    private function baselineLabel(float $deltaVsAverage, int $deltaVsBest): string
    {
        if ($deltaVsBest > 0) {
            return '+'.$deltaVsBest.' dari rekor formatmu';
        }

        if ($deltaVsAverage > 0) {
            return '+'.$deltaVsAverage.' dari rata-ratamu';
        }

        if ($deltaVsAverage === 0.0) {
            return 'Setara rata-ratamu';
        }

        return abs($deltaVsAverage).' di bawah rata-ratamu';
    }
}
