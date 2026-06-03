<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventDivision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventDivision
 */
class EventDivisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'bow_class' => $this->bow_class->value,
            'gender' => $this->gender->value,
            'age_group' => $this->age_group->value,
            'distance_category' => $this->distance_category->value,
            'distance_m' => $this->distance_m,
            'num_arrows' => $this->num_arrows,
            'max_score' => $this->max_score,
            'entry_fee' => $this->entry_fee,
            'capacity' => $this->capacity,
            'num_participants' => $this->num_participants,
            'sof_avg_rating' => $this->sof_avg_rating,
            'rating_status' => $this->rating_status,
            'rated_at' => $this->rated_at?->toIso8601String(),
        ];
    }
}
