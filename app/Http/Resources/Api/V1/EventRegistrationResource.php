<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventRegistration
 */
class EventRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_division_id' => $this->event_division_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'user_avatar_url' => $this->user->avatarAsset?->getPublicUrl(),
            'status' => $this->status->value,
            'payment_id' => $this->payment_id,
            'bib_number' => $this->bib_number,
            'qr_code' => $this->qr_code,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relations
            'division' => new EventDivisionResource($this->whenLoaded('division')),
            'event' => $this->relationLoaded('division') && $this->division->relationLoaded('event')
                ? new EventResource($this->division->event)
                : null,
        ];
    }
}
