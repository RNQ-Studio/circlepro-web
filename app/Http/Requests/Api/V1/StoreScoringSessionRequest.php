<?php

namespace App\Http\Requests\Api\V1;

use App\Support\ScoringSessionRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreScoringSessionRequest extends FormRequest
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
        return ScoringSessionRules::rules(userId: $this->user()?->id);
    }
}
