<?php

namespace App\Models;

use App\Support\Enums\ArcheryEnvironment;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\ParticipationStatus;
use App\Support\Enums\ScoringSessionStatus;
use App\Support\Enums\SyncSource;
use Database\Factories\ScoringSessionFactory;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int|null $user_id NULL = guest participant (group practice)
 * @property string|null $guest_name display name for a player without an account
 * @property int|null $added_by_user_id who created this participant row (host)
 * @property string|null $equipment_profile_id
 * @property string|null $organization_id
 * @property string|null $event_division_id
 * @property string|null $scoring_session_group_id
 * @property ParticipationStatus|null $participation_status
 * @property string|null $title
 * @property BowClass|null $bow_class
 * @property DistanceCategory|null $distance_category
 * @property int $distance_m
 * @property ArcheryEnvironment|null $environment
 * @property int|null $target_face_cm
 * @property string|null $target_face_id
 * @property int|null $target_butt bantalan number (Phase 3)
 * @property string|null $target_letter target position A/B/C/D (Phase 3)
 * @property int $num_ends
 * @property int $arrows_per_end
 * @property ScoringSessionStatus|null $status
 * @property int $total_score
 * @property int $max_possible_score
 * @property int $arrows_shot
 * @property float|null $avg_per_arrow
 * @property int $x_count
 * @property int $ten_count
 * @property int $miss_count
 * @property bool $is_personal_best
 * @property string|null $notes
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property string|null $client_uuid
 * @property SyncSource|null $source
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ScoringEnd> $ends
 * @property-read ScoringSessionGroup|null $group
 * @property-read User|null $addedBy
 * @property-read Collection<int, ScoringSessionClaim> $claims
 */
class ScoringSession extends Model
{
    /** @use HasFactory<ScoringSessionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'guest_name',
        'added_by_user_id',
        'equipment_profile_id',
        'organization_id',
        'event_division_id',
        'scoring_session_group_id',
        'participation_status',
        'title',
        'bow_class',
        'distance_category',
        'distance_m',
        'environment',
        'target_face_cm',
        'target_face_id',
        'target_butt',
        'target_letter',
        'num_ends',
        'arrows_per_end',
        'status',
        'notes',
        'started_at',
        'completed_at',
        'client_uuid',
        'source',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'bow_class' => BowClass::class,
            'distance_category' => DistanceCategory::class,
            'environment' => ArcheryEnvironment::class,
            'status' => ScoringSessionStatus::class,
            'participation_status' => ParticipationStatus::class,
            'source' => SyncSource::class,
            'is_personal_best' => 'boolean',
            'avg_per_arrow' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<EquipmentProfile, $this> */
    public function equipmentProfile(): BelongsTo
    {
        return $this->belongsTo(EquipmentProfile::class);
    }

    /** @return BelongsTo<TargetFace, $this> */
    public function targetFace(): BelongsTo
    {
        return $this->belongsTo(TargetFace::class);
    }

    /** @return HasMany<ScoringEnd, $this> */
    public function ends(): HasMany
    {
        return $this->hasMany(ScoringEnd::class)->orderBy('end_number');
    }

    /** @return BelongsTo<ScoringSessionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ScoringSessionGroup::class, 'scoring_session_group_id');
    }

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /** @return HasMany<ScoringSessionClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(ScoringSessionClaim::class);
    }

    /** A participant row with no owner yet (group practice guest). */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /** @param Builder<ScoringSession> $query */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /** @param Builder<ScoringSession> $query */
    public function scopeStartedAfter(Builder $query, string $date): void
    {
        $query->whereDate('started_at', '>=', $date);
    }

    /** @param Builder<ScoringSession> $query */
    public function scopeStartedBefore(Builder $query, string $date): void
    {
        $query->whereDate('started_at', '<=', $date);
    }
}
