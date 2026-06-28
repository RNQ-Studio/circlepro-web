<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ScoringSessionGroup;
use Illuminate\Foundation\Http\FormRequest;

class AssignGroupScorerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        abort_unless(
            $group instanceof ScoringSessionGroup
                && ($this->user()?->can('manage', $group) ?? false),
            404,
            'Resource not found.',
        );

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'target_butt' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
