<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class ClubResource extends JsonResource
{
    /** Current user's role in this club (null if not a member). */
    public ?string $myRole = null;

    public bool $isMember = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'banner_url' => $this->banner_url,
            'province' => $this->province,
            'city' => $this->city,
            'address' => $this->address,
            'is_verified' => $this->is_verified,
            'member_count' => $this->member_count ?? $this->members()->where('status', 'active')->count(),
            'my_role' => $this->myRole,
            'is_member' => $this->isMember,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
