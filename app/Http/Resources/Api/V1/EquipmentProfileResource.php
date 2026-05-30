<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EquipmentProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentProfile
 */
class EquipmentProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bow_class' => $this->bow_class->value,
            'bow_model' => $this->bow_model,
            'draw_weight_lbs' => $this->draw_weight_lbs,
            'arrow_spec' => $this->arrow_spec,
            'tuning_notes' => $this->tuning_notes,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
