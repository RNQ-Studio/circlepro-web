<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Approve or reject a claim — Sprint 13, tasks 13.3/13.5. Host-only; the host
 * check itself happens in the controller (a denied check becomes a 404 for
 * privacy, mirroring the rest of the group API).
 */
class ResolveScoringSessionClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
        ];
    }
}
