<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ScoringSessionGroup;
use Illuminate\Foundation\Http\FormRequest;

class ClaimGroupScorerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        abort_unless(
            $group instanceof ScoringSessionGroup
                && ($this->user()?->can('view', $group) ?? false),
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
            'target_butt' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
