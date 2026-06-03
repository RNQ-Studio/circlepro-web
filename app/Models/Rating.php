<?php

namespace App\Models;

use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\Gender;
use App\Support\Enums\RatingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property int $user_id
 * @property BowClass $bow_class
 * @property Gender $gender
 * @property AgeGroup $age_group
 * @property DistanceCategory $distance_category
 * @property float $mu
 * @property float $phi
 * @property float $sigma
 * @property float $display_rating
 * @property RatingStatus $status
 * @property int $events_count
 * @property float|null $peak_display_rating
 * @property Carbon|null $last_event_date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Rating extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'bow_class',
        'gender',
        'age_group',
        'distance_category',
        'mu',
        'phi',
        'sigma',
        'display_rating',
        'status',
        'events_count',
        'peak_display_rating',
        'last_event_date',
    ];

    protected function casts(): array
    {
        return [
            'bow_class' => BowClass::class,
            'gender' => Gender::class,
            'age_group' => AgeGroup::class,
            'distance_category' => DistanceCategory::class,
            'status' => RatingStatus::class,
            'mu' => 'float',
            'phi' => 'float',
            'sigma' => 'float',
            'display_rating' => 'float',
            'peak_display_rating' => 'float',
            'last_event_date' => 'date',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RatingHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(RatingHistory::class);
    }
}
