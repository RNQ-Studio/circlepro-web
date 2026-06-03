<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CoachProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CoachProfile
 */
class CoachProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bio' => $this->bio,
            'specialties' => $this->specialties,
            'certification' => $this->certification,
            'experience_years' => $this->experience_years,
            'hourly_rate' => $this->hourly_rate,
            'whatsapp_number' => $this->whatsapp_number,
            'is_verified' => $this->is_verified,
            'availability' => $this->availability,
            'average_rating' => $this->average_rating,
            'reviews_count' => $this->reviews_count,
            'user' => [
                'id' => $this->user?->id,
                'full_name' => $this->user?->full_name ?? $this->user?->name,
                'username' => $this->user?->username,
                'avatar_url' => $this->user?->profile?->avatar_url,
                'city' => $this->user?->profile?->city,
                'province' => $this->user?->profile?->province,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
