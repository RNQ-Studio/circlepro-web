<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\BowClass;
use App\Support\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ManahPro rich profile update (Module 0/2) — distinct from the starter's
 * minimal AuthController `UpdateProfileRequest`.
 */
class UpdateUserProfileRequest extends FormRequest
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
            'full_name' => ['sometimes', 'string', 'max:120'],
            'username' => [
                'sometimes', 'nullable', 'string', 'max:40', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($this->user()->id),
            ],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'banner_url' => ['nullable', 'string', 'max:2048'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'primary_bow_class' => ['nullable', Rule::enum(BowClass::class)],
            'home_club_id' => ['nullable', 'ulid', Rule::exists('organizations', 'id')],
            'social_links' => ['nullable', 'array'],
        ];
    }
}
