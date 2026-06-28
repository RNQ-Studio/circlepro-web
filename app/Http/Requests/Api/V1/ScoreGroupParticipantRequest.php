<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Support\Enums\ScoringSessionStatus;
use App\Support\ScoringSessionRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Score one participant row of a group — Sprint 03, task 3.1/3.2. The round
 * format is inherited from the group, so only the ends/arrows (+ optional
 * status & idempotency key) are accepted here. Authorization (Phase 0 matrix
 * §4, Sprint 17): the host of the group OR the owner of this very session OR
 * the scorer assigned to this row's bantalan. A denied check returns 404
 * (privacy) before validation, mirroring the rest of the module.
 */
class ScoreGroupParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');
        $session = $this->route('session');

        $allowed = $group instanceof ScoringSessionGroup
            && $session instanceof ScoringSession
            && $session->scoring_session_group_id === $group->id
            && $this->actorMayScore($group, $session);

        abort_unless($allowed, 404, 'Resource not found.');

        return true;
    }

    private function actorMayScore(ScoringSessionGroup $group, ScoringSession $session): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $isHost = $user->can('manage', $group);
        $isOwner = $session->user_id !== null && $session->user_id === $user->id;
        $isAssignedScorer = $session->target_butt !== null
            && $group->scorers()
                ->where('user_id', $user->id)
                ->where('target_butt', $session->target_butt)
                ->exists();

        return $isHost || $isOwner || $isAssignedScorer;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::enum(ScoringSessionStatus::class)],
            'completed_at' => ['nullable', 'date'],
        ] + ScoringSessionRules::endsRules();
    }
}
