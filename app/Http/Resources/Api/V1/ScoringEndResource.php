<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ScoringEnd;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScoringEnd
 */
class ScoringEndResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'end_number' => $this->end_number,
            'is_sighter' => (bool) $this->is_sighter,
            'end_total' => $this->end_total,
            'arrows' => ScoringArrowResource::collection($this->whenLoaded('arrows')),
        ];
    }
}
