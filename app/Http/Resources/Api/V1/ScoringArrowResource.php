<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ScoringArrow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScoringArrow
 */
class ScoringArrowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'arrow_index' => $this->arrow_index,
            'score_value' => $this->score_value,
            'is_x' => $this->is_x,
            'is_miss' => $this->is_miss,
            'pos_x' => $this->pos_x,
            'pos_y' => $this->pos_y,
        ];
    }
}
