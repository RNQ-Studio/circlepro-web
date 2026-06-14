<?php

namespace App\Support\Enums;

/**
 * How a participant joined a group scoring session
 * (scoring_sessions.participation_status). Distinguishes a self-joined
 * archer from one added by the host or invited — the root of consent
 * concern R4. Used from Phase 1/2 onward.
 */
enum ParticipationStatus: string
{
    case Self = 'self';
    case HostAdded = 'host_added';
    case Invited = 'invited';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'Gabung sendiri',
            self::HostAdded => 'Ditambahkan host',
            self::Invited => 'Diundang',
        };
    }
}
