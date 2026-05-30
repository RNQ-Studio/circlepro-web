<?php

namespace App\Http\Requests\Api\V1;

use App\Support\ScoringSessionRules;
use Illuminate\Foundation\Http\FormRequest;

class SyncScoringSessionsRequest extends FormRequest
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
            'sessions' => ['required', 'array', 'min:1', 'max:50'],
            ...ScoringSessionRules::rules('sessions.*.', $this->user()?->id),
        ];
    }
}
