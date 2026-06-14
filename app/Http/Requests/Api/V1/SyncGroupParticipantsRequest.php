<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ScoringSessionGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\ScoringSessionStatus;
use App\Support\ScoringSessionRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Idempotent batch sync of participant scores for one group — Sprint 03,
 * task 3.3. Each item carries the participant's client-generated id/client_uuid
 * (for resolve-or-create) plus its ends/arrows, so an offline host can reconcile
 * a whole board in one forgiving call. Authorization: caller must be able to
 * view the group (host or participant); per-row write permission is enforced in
 * the service (only the host may mint new rows / write guests in Phase 0).
 */
class SyncGroupParticipantsRequest extends FormRequest
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
            'sessions' => ['required', 'array', 'min:1', 'max:100'],
            'sessions.*.id' => ['nullable', 'string', 'ulid'],
            'sessions.*.client_uuid' => ['nullable', 'uuid'],
            // Lets an offline-created guest row be minted on first sync (host).
            'sessions.*.name' => ['nullable', 'string', 'max:100'],
            'sessions.*.bow_class' => ['nullable', Rule::enum(BowClass::class)],
            'sessions.*.distance_category' => ['nullable', Rule::enum(DistanceCategory::class)],
            'sessions.*.distance_m' => ['nullable', 'integer', 'min:1', 'max:900'],
            'sessions.*.target_face_cm' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sessions.*.target_butt' => ['nullable', 'integer', 'min:1', 'max:200'],
            'sessions.*.target_letter' => ['nullable', 'string', 'size:1'],
            'sessions.*.status' => ['nullable', Rule::enum(ScoringSessionStatus::class)],
            'sessions.*.completed_at' => ['nullable', 'date'],
        ] + ScoringSessionRules::endsRules('sessions.*.');
    }
}
