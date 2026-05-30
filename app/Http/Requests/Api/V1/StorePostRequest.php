<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\PostVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'body' => ['nullable', 'required_without:shared_id', 'string', 'max:5000'],
            'visibility' => ['nullable', Rule::enum(PostVisibility::class)],
            'organization_id' => ['nullable', 'ulid', Rule::exists('organizations', 'id')],
            'shared_type' => ['nullable', 'required_with:shared_id', Rule::in(['scoring_session', 'event', 'product'])],
            'shared_id' => ['nullable', 'required_with:shared_type', 'ulid'],
        ];
    }
}
