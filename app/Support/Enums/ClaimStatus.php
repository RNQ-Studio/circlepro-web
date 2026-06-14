<?php

namespace App\Support\Enums;

/**
 * Lifecycle of a guest-slot claim (scoring_session_claims.status).
 * A claimant requests ownership of a guest participant row; the host
 * approves or rejects. Used by the claim flow (Phase 2).
 */
enum ClaimStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
