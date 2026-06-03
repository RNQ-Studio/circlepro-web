<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CoachReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CoachReview
 */
class CoachReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coach_profile_id' => $this->coach_profile_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'user' => [
                'id' => $this->user?->id,
                'full_name' => $this->user?->full_name ?? $this->user?->name,
                'username' => $this->user?->username,
                'avatar_url' => $this->user?->profile?->avatar_url,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
