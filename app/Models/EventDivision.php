<?php

namespace App\Models;

use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\Gender;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property BowClass $bow_class
 * @property Gender $gender
 * @property AgeGroup $age_group
 * @property DistanceCategory $distance_category
 * @property int $distance_m
 * @property int $num_arrows
 * @property int $max_score
 * @property int $entry_fee
 * @property int|null $capacity
 * @property int $num_participants
 * @property float|null $sof_avg_rating
 * @property string $rating_status
 * @property Carbon|null $rated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventRegistration> $registrations
 */
class EventDivision extends Model
{
    use HasFactory, HasUlids;

    protected $attributes = [
        'rating_status' => 'unrated',
    ];

    protected $fillable = [
        'id',
        'event_id',
        'bow_class',
        'gender',
        'age_group',
        'distance_category',
        'distance_m',
        'num_arrows',
        'max_score',
        'entry_fee',
        'capacity',
        'num_participants',
        'sof_avg_rating',
        'rating_status',
        'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'bow_class' => BowClass::class,
            'gender' => Gender::class,
            'age_group' => AgeGroup::class,
            'distance_category' => DistanceCategory::class,
            'entry_fee' => 'integer',
            'capacity' => 'integer',
            'num_participants' => 'integer',
            'sof_avg_rating' => 'float',
            'rated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }
}
