<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ScoringSessionGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Batch quick-add of guests (Sprint 02, task 2.4 / K8): many names in one
 * request, metadata optional so nothing blocks adding a person. Host-only;
 * a denied check returns 404 (privacy) before validation. An optional
 * client_uuid (or client-generated id) makes a re-sent row idempotent.
 */
class AddGroupParticipantsRequest extends FormRequest
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
            'participants' => ['required', 'array', 'min:1', 'max:50'],
            'participants.*.name' => ['required', 'string', 'max:100'],
            'participants.*.bow_class' => ['nullable', Rule::enum(BowClass::class)],
            'participants.*.distance_category' => ['nullable', Rule::enum(DistanceCategory::class)],
            'participants.*.distance_m' => ['nullable', 'integer', 'min:1', 'max:900'],
            'participants.*.target_face_cm' => ['nullable', 'integer', 'min:1', 'max:200'],
            'participants.*.target_butt' => ['nullable', 'integer', 'min:1', 'max:200'],
            'participants.*.target_letter' => ['nullable', 'string', 'size:1'],
            'participants.*.client_uuid' => ['nullable', 'uuid'],
            'participants.*.id' => ['nullable', 'string', 'ulid'],
        ];
    }
}
