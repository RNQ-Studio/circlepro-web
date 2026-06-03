<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ArcheryRange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ArcheryRange
 */
class ArcheryRangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'facilities' => $this->facilities,
            'phone' => $this->phone,
            'price_per_hour' => $this->price_per_hour,
            'image_url' => $this->image_url,
            'distance' => isset($this->distance) ? round((float) $this->distance, 2) : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
