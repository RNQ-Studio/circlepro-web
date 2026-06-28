<?php

namespace App\Policies;

use App\Models\ScoringSessionGroup;
use App\Models\User;

/**
 * Authorization for Latihan Bersama groups (§4 matrix, Phase 0 columns only:
 * Host & Pemilik). Host-only actions use {@see manage()}; viewing the
 * detail/roster is open to the host and any participant. Callers translate a
 * denied check into a 404 (privacy) rather than a 403, mirroring
 * ScoringSessionController. super-admin bypasses via the global Gate::before.
 */
class ScoringSessionGroupPolicy
{
    /** Host or a participant may see the group detail, roster & leaderboard. */
    public function view(User $user, ScoringSessionGroup $group): bool
    {
        return $this->isHost($user, $group)
            || $this->isParticipant($user, $group)
            || $this->isScorer($user, $group);
    }

    /**
     * Only the host may add/remove participants, edit the round format and
     * finish/abandon the group.
     */
    public function manage(User $user, ScoringSessionGroup $group): bool
    {
        return $this->isHost($user, $group);
    }

    private function isHost(User $user, ScoringSessionGroup $group): bool
    {
        return $group->host_user_id === $user->id;
    }

    private function isParticipant(User $user, ScoringSessionGroup $group): bool
    {
        return $group->participants()->where('user_id', $user->id)->exists();
    }

    private function isScorer(User $user, ScoringSessionGroup $group): bool
    {
        return $group->scorers()->where('user_id', $user->id)->exists();
    }
}
