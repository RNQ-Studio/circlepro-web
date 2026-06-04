<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class StoryGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->id,
                'full_name' => $this->full_name ?? $this->name,
                'username' => $this->username,
                'avatar_url' => $this->profile?->avatar_url,
            ],
            'stories' => StoryResource::collection($this->whenLoaded('stories')),
        ];
    }
}
