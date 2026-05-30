<?php

namespace App\Models;

use App\Support\Enums\ArcheryEnvironment;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\ScoringSessionStatus;
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
 * @property int $num_ends
 * @property int $arrows_per_end
 * @property string $join_code
 * @property ScoringSessionStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ScoringSessionGroup extends Model
{
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

    /** @return HasMany<ScoringSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(ScoringSession::class);
    }
}
