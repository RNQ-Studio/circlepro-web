<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ScoringSessionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScoringSessionGroup
 */
class ScoringSessionGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'host' => [
                'id' => $this->host_user_id,
                'name' => $this->host->name,
            ],
            'title' => $this->title,
            // Full round format — the lookup preview needs this, not just the
            // social metadata (task 2.3).
            'distance_category' => $this->distance_category->value,
            'distance_m' => $this->distance_m,
            'environment' => $this->environment->value,
            'target_face_cm' => $this->target_face_cm,
            'target_face_id' => $this->target_face_id,
            'num_ends' => $this->num_ends,
            'arrows_per_end' => $this->arrows_per_end,
            'join_code' => $this->join_code,
            'status' => $this->status->value,
            'participant_count' => $this->whenCounted('participants'),
            'started_at' => $this->started_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Roster only when explicitly loaded (detail), never on lookup.
            'participants' => GroupParticipantResource::collection($this->whenLoaded('participants')),
        ];
    }
}
