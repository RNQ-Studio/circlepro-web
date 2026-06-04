<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Rating;
use App\Models\RatingBand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Rating
 */
class RatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Resolve rating band/title
        $band = RatingBand::where(function ($query) {
            $query->where('organization_id', $this->organization_id)
                ->orWhereNull('organization_id');
        })
            ->where('min_display_rating', '<=', $this->display_rating)
            ->where(function ($query) {
                $query->whereNull('max_display_rating')
                    ->orWhere('max_display_rating', '>=', $this->display_rating);
            })
            ->orderByDesc('organization_id') // Specific organization takes precedence
            ->first();

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'username' => $this->user?->username,
            'avatar_url' => $this->user?->profile?->avatar_url,
            'bow_class' => $this->bow_class->value,
            'gender' => $this->gender->value,
            'age_group' => $this->age_group->value,
            'distance_category' => $this->distance_category->value,
            'mu' => $this->mu,
            'phi' => $this->phi,
            'sigma' => $this->sigma,
            'display_rating' => $this->display_rating,
            'status' => $this->status->value,
            'events_count' => $this->events_count,
            'peak_display_rating' => $this->peak_display_rating,
            'last_event_date' => $this->last_event_date?->toDateString(),
            'title' => $band?->title ?? 'Novice',
            'badge' => $band?->badge ?? 'Starter',
            'color' => $band?->color ?? 'default',
        ];
    }
}
