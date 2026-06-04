<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TargetFace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TargetFace
 */
class TargetFaceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'organization' => $this->organization ? [
                'id' => $this->organization->id,
                'slug' => $this->organization->slug,
                'name' => $this->organization->name,
            ] : null,
            'code' => $this->code,
            'name' => $this->name,
            'image_path' => $this->image_path,
            'used_count' => $this->used_count,
            'scoring_rules' => $this->scoring_rules,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
