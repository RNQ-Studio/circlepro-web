<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use Illuminate\Foundation\Http\FormRequest;

class AssignParticipantDistanceRequest extends FormRequest
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
            'distance_m' => ['sometimes', 'integer', 'min:1', 'max:900'],
            'target_face_cm' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
