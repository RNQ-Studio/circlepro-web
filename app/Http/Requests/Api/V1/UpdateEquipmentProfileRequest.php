<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\BowClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'bow_class' => ['sometimes', Rule::enum(BowClass::class)],
            'bow_model' => ['nullable', 'string', 'max:100'],
            'draw_weight_lbs' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'arrow_spec' => ['nullable', 'string', 'max:120'],
            'tuning_notes' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
