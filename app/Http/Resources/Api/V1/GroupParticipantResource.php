<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ScoringSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A roster entry of a group: one scoring_sessions row, owner or guest. Score
 * aggregates are recomputed server-side on every score/sync (Sprint 03).
 *
 * @mixin ScoringSession
 */
class GroupParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'is_guest' => $this->isGuest(),
            'display_name' => $this->guest_name ?? $this->user?->name,
            'guest_name' => $this->guest_name,
            'added_by_user_id' => $this->added_by_user_id,
            'last_scored_by_user_id' => $this->last_scored_by_user_id,
            'participation_status' => $this->participation_status?->value,
            'bow_class' => $this->bow_class?->value,
            'distance_category' => $this->distance_category?->value,
            'distance_m' => $this->distance_m,
            'target_face_cm' => $this->target_face_cm,
            'target_butt' => $this->target_butt,
            'target_letter' => $this->target_letter,
            'status' => $this->status?->value,
            'total_score' => $this->total_score,
            'max_possible_score' => $this->max_possible_score,
            'arrows_shot' => $this->arrows_shot,
            'x_count' => $this->x_count,
            'ten_count' => $this->ten_count,
            'miss_count' => $this->miss_count,
            'avg_per_arrow' => $this->avg_per_arrow,
            // PB only ever flips for an owned row; a guest stays false (§3.2).
            'is_personal_best' => $this->is_personal_best,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'synced_at' => $this->synced_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
