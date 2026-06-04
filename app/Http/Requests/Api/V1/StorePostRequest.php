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
            'body' => ['nullable', 'required_without_all:shared_id,media,poll', 'string', 'max:5000'],
            'visibility' => ['nullable', Rule::enum(PostVisibility::class)],
            'organization_id' => ['nullable', 'ulid', Rule::exists('organizations', 'id')],
            'shared_type' => ['nullable', 'required_with:shared_id', Rule::in(['scoring_session', 'event', 'product'])],
            'shared_id' => ['nullable', 'required_with:shared_type', 'ulid'],
            'media' => ['nullable', 'array'],
            'media.*.url' => ['required_with:media', 'string', 'url'],
            'media.*.type' => ['required_with:media', 'string', 'in:image,video'],
            'media.*.position' => ['nullable', 'integer', 'min:0'],
            'poll' => ['nullable', 'array'],
            'poll.question' => ['required_with:poll', 'string', 'max:255'],
            'poll.options' => ['required_with:poll', 'array', 'min:2', 'max:10'],
            'poll.options.*' => ['required_with:poll', 'string', 'max:100'],
            'poll.expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
