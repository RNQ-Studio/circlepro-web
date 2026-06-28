<?php

namespace App\Http\Resources\Api\V1;

use App\Models\GroupScorer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One scorer assignment for a Latihan Bersama bantalan.
 *
 * @mixin GroupScorer
 */
class GroupScorerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scoring_session_group_id' => $this->scoring_session_group_id,
            'user_id' => $this->user_id,
            'target_butt' => $this->target_butt,
            'assignment_type' => $this->assignment_type->value,
            'assigned_by_user_id' => $this->assigned_by_user_id,
            'scorer' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'assigned_by' => $this->whenLoaded('assignedBy', fn (): ?array => $this->assignedBy === null
                ? null
                : [
                    'id' => $this->assignedBy->id,
                    'name' => $this->assignedBy->name,
                ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
