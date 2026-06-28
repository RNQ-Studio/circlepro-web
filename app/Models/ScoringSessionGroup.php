<?php

namespace App\Models;

use App\Support\Enums\ArcheryEnvironment;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\ScoringSessionStatus;
use Database\Factories\ScoringSessionGroupFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $host_user_id
 * @property string|null $organization_id
 * @property string|null $title
 * @property DistanceCategory $distance_category
 * @property int $distance_m
 * @property ArcheryEnvironment $environment
 * @property int|null $target_face_cm
 * @property string|null $target_face_id
 * @property int $num_ends
 * @property int $arrows_per_end
 * @property string $join_code
 * @property ScoringSessionStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $host
 * @property-read Collection<int, ScoringSession> $participants
 * @property-read Collection<int, ScoringSession> $sessions
 * @property-read Collection<int, ScoringSessionClaim> $claims
 * @property-read Collection<int, GroupScorer> $scorers
 */
class ScoringSessionGroup extends Model
{
    /** @use HasFactory<ScoringSessionGroupFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'id',
        'host_user_id',
        'organization_id',
        'title',
        'distance_category',
        'distance_m',
        'environment',
        'target_face_cm',
        'target_face_id',
        'num_ends',
        'arrows_per_end',
        'join_code',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'distance_category' => DistanceCategory::class,
            'environment' => ArcheryEnvironment::class,
            'status' => ScoringSessionStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /** @return BelongsTo<TargetFace, $this> */
    public function targetFace(): BelongsTo
    {
        return $this->belongsTo(TargetFace::class);
    }

    /**
     * Participant rows of this group. Each participant IS a scoring_sessions
     * row (owner or guest) — the binder philosophy (§1).
     *
     * @return HasMany<ScoringSession, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ScoringSession::class);
    }

    /**
     * Alias of {@see participants()} kept for readability where the rows are
     * referred to as sessions rather than participants.
     *
     * @return HasMany<ScoringSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ScoringSession::class);
    }

    /** @return HasMany<ScoringSessionClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(ScoringSessionClaim::class);
    }

    /** @return HasMany<GroupScorer, $this> */
    public function scorers(): HasMany
    {
        return $this->hasMany(GroupScorer::class);
    }
}
