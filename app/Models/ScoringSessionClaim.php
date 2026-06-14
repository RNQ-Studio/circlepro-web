<?php

namespace App\Models;

use App\Support\Enums\ClaimStatus;
use Database\Factories\ScoringSessionClaimFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A signed-in archer's claim over a guest participant slot, awaiting host
 * approval ("Ini Saya"). On approval ownership transfers to the claimant
 * (Phase 2 / Sprint 13). See §3.2.
 *
 * @property string $id
 * @property string $scoring_session_id
 * @property string $scoring_session_group_id
 * @property int $claimant_user_id
 * @property ClaimStatus $status
 * @property string|null $message
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ScoringSession $session
 * @property-read ScoringSessionGroup $group
 * @property-read User $claimant
 * @property-read User|null $resolvedBy
 */
class ScoringSessionClaim extends Model
{
    /** @use HasFactory<ScoringSessionClaimFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'scoring_session_id',
        'scoring_session_group_id',
        'claimant_user_id',
        'status',
        'message',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ScoringSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ScoringSession::class, 'scoring_session_id');
    }

    /** @return BelongsTo<ScoringSessionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ScoringSessionGroup::class, 'scoring_session_group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
