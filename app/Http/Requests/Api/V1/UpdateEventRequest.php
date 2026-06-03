<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\EventFormat;
use App\Support\Enums\EventStatus;
use App\Support\Enums\EventTier;
use App\Support\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'banner_url' => ['nullable', 'string', 'max:2048'],
            'tier' => ['nullable', 'string', Rule::enum(EventTier::class)],
            'format' => ['nullable', 'string', Rule::enum(EventFormat::class)],
            'status' => ['nullable', 'string', Rule::enum(EventStatus::class)],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'venue_name' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'schedule' => ['nullable', 'array'],
            'rules' => ['nullable', 'string'],
            'is_external' => ['nullable', 'boolean'],

            'divisions' => ['nullable', 'array'],
            'divisions.*.id' => ['nullable', 'string', 'exists:event_divisions,id'],
            'divisions.*.bow_class' => ['required_with:divisions.*.id', 'string', Rule::enum(BowClass::class)],
            'divisions.*.gender' => ['required_with:divisions.*.id', 'string', Rule::enum(Gender::class)],
            'divisions.*.age_group' => ['required_with:divisions.*.id', 'string', Rule::enum(AgeGroup::class)],
            'divisions.*.distance_category' => ['required_with:divisions.*.id', 'string', Rule::enum(DistanceCategory::class)],
            'divisions.*.distance_m' => ['required_with:divisions.*.id', 'integer', 'min:1'],
            'divisions.*.num_arrows' => ['required_with:divisions.*.id', 'integer', 'min:1'],
            'divisions.*.max_score' => ['required_with:divisions.*.id', 'integer', 'min:1'],
            'divisions.*.entry_fee' => ['required_with:divisions.*.id', 'integer', 'min:0'],
            'divisions.*.capacity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
