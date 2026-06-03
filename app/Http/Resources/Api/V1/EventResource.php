<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'organization_name' => $this->organization?->name,
            'organization_logo' => $this->organization?->logo_url,
            'created_by' => $this->created_by,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'banner_url' => $this->banner_url,
            'tier' => $this->tier->value,
            'format' => $this->format->value,
            'status' => $this->status->value,
            'province' => $this->province,
            'city' => $this->city,
            'venue_name' => $this->venue_name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'registration_opens_at' => $this->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $this->registration_closes_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'schedule' => $this->schedule,
            'rules' => $this->rules,
            'is_external' => $this->is_external,
            'published_at' => $this->published_at?->toIso8601String(),
            'divisions' => EventDivisionResource::collection($this->whenLoaded('divisions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
