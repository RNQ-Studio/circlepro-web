<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrganizationMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizationMember
 */
class ClubMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user?->id,
                'full_name' => $this->user?->full_name ?? $this->user?->name,
                'username' => $this->user?->username,
                'avatar_url' => $this->user?->profile?->avatar_url,
            ],
            'role' => $this->role->value,
            'status' => $this->status,
            'member_code' => $this->member_code,
            'joined_at' => $this->joined_at?->toIso8601String(),
        ];
    }
}
