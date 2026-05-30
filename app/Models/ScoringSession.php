<?php

namespace App\Models;

use App\Support\Enums\ArcheryEnvironment;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
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
 * @property int $user_id
 * @property string|null $equipment_profile_id
 * @property string|null $organization_id
 * @property string|null $event_division_id
 * @property string|null $scoring_session_group_id
 * @property string|null $title
 * @property BowClass $bow_class
 * @property DistanceCategory $distance_category
 * @property int $distance_m
 * @property ArcheryEnvironment $environment
 * @property int|null $target_face_cm
 * @property int $num_ends
 * @property int $arrows_per_end
 * @property ScoringSessionStatus $status
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
 * @property SyncSource $source
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ScoringEnd> $ends
 */
class ScoringSession extends Model
{
    /** @use HasFactory<ScoringSessionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'equipment_profile_id',
        'organization_id',
        'event_division_id',
        'scoring_session_group_id',
        'title',
        'bow_class',
        'distance_category',
        'distance_m',
        'environment',
        'target_face_cm',
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

    /** @return HasMany<ScoringEnd, $this> */
    public function ends(): HasMany
    {
        return $this->hasMany(ScoringEnd::class)->orderBy('end_number');
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
