<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Enums\BowClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Self-join a group via its link/QR/code — Sprint 10, task 10.1. Any
 * authenticated user may join (K7: a "real" user joins for themselves; consent
 * is automatic because it is their own row). The roster row is created with
 * participation_status = self. Bow class is optional (K8: metadata must never
 * block joining); a client-generated id/client_uuid keeps the join idempotent
 * and offline-friendly.
 */
class JoinScoringSessionGroupRequest extends FormRequest
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
            'id' => ['nullable', 'string', 'size:26'],
            'client_uuid' => ['nullable', 'uuid'],
            'bow_class' => ['nullable', Rule::enum(BowClass::class)],
        ];
    }
}
