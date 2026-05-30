<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ScoringSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScoringSession
 */
class ScoringSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_profile_id' => $this->equipment_profile_id,
            'organization_id' => $this->organization_id,
            'event_division_id' => $this->event_division_id,
            'scoring_session_group_id' => $this->scoring_session_group_id,
            'title' => $this->title,
            'bow_class' => $this->bow_class->value,
            'distance_category' => $this->distance_category->value,
            'distance_m' => $this->distance_m,
            'environment' => $this->environment->value,
            'target_face_cm' => $this->target_face_cm,
            'num_ends' => $this->num_ends,
            'arrows_per_end' => $this->arrows_per_end,
            'status' => $this->status->value,
            'total_score' => $this->total_score,
            'max_possible_score' => $this->max_possible_score,
            'arrows_shot' => $this->arrows_shot,
            'avg_per_arrow' => $this->avg_per_arrow,
            'x_count' => $this->x_count,
            'ten_count' => $this->ten_count,
            'miss_count' => $this->miss_count,
            'is_personal_best' => $this->is_personal_best,
            'notes' => $this->notes,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'client_uuid' => $this->client_uuid,
            'source' => $this->source->value,
            'synced_at' => $this->synced_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'ends' => ScoringEndResource::collection($this->whenLoaded('ends')),
        ];
    }
}
