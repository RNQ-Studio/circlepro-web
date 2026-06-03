<?php

namespace App\Http\Resources\Api\V1;

use App\Models\RatingHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RatingHistory
 */
class RatingHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating_id' => $this->rating_id,
            'user_id' => $this->user_id,
            'mu_before' => $this->mu_before,
            'mu_after' => $this->mu_after,
            'phi_before' => $this->phi_before,
            'phi_after' => $this->phi_after,
            'sigma_before' => $this->sigma_before,
            'sigma_after' => $this->sigma_after,
            'display_before' => $this->display_before,
            'display_after' => $this->display_after,
            'display_change' => $this->display_after - $this->display_before,
            'score_achieved' => $this->score_achieved,
            'nps' => $this->nps,
            'placement' => $this->placement,
            'num_participants' => $this->num_participants,
            'event_tier' => $this->event_tier?->value,
            'k_effective' => $this->k_effective,
            'is_manual_override' => $this->is_manual_override,
            'event_id' => $this->eventDivision?->event_id,
            'event_name' => $this->eventDivision?->event?->title,
            'division_name' => $this->eventDivision?->displayName,
            'computed_at' => $this->computed_at->toIso8601String(),
        ];
    }
}
