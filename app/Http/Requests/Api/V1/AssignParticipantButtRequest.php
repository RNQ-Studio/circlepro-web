<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Move a participant between bantalan — Sprint 16, task 16.2. Setting a butt is
 * roster bookkeeping, so it follows the same gate as removing a participant
 * (§4 matrix): the host may move anyone, an archer may move their own row. A
 * denied check returns 404 (privacy) before validation, mirroring the module.
 * Passing target_butt = null un-maps the participant.
 */
class AssignParticipantButtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');
        $session = $this->route('session');

        $allowed = $group instanceof ScoringSessionGroup
            && $session instanceof ScoringSession
            && $session->scoring_session_group_id === $group->id
            && $this->actorMayAssign($group, $session);

        abort_unless($allowed, 404, 'Resource not found.');

        return true;
    }

    private function actorMayAssign(ScoringSessionGroup $group, ScoringSession $session): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $isHost = $user->can('manage', $group);
        $isOwnRow = $session->user_id !== null && $session->user_id === $user->id;

        return $isHost || $isOwnRow;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_butt' => ['present', 'nullable', 'integer', 'min:1', 'max:200'],
            'target_letter' => ['nullable', 'string', 'size:1'],
        ];
    }
}
