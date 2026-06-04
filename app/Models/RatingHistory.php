<?php

namespace App\Models;

use App\Support\Enums\EventTier;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $rating_id
 * @property int $user_id
 * @property string|null $event_division_id
 * @property string|null $rating_period_id
 * @property float $mu_before
 * @property float $mu_after
 * @property float $phi_before
 * @property float $phi_after
 * @property float $sigma_before
 * @property float $sigma_after
 * @property float $display_before
 * @property float $display_after
 * @property int|null $score_achieved
 * @property float|null $nps
 * @property int|null $placement
 * @property int|null $num_participants
 * @property EventTier|null $event_tier
 * @property float|null $k_effective
 * @property bool $is_manual_override
 * @property Carbon $computed_at
 * @property Carbon $created_at
 */
class RatingHistory extends Model
{
    use HasUlids;

    protected $table = 'rating_history';

    protected $fillable = [
        'rating_id',
        'user_id',
        'event_division_id',
        'rating_period_id',
        'is_calibration',
        'mu_before',
        'mu_after',
        'phi_before',
        'phi_after',
        'sigma_before',
        'sigma_after',
        'display_before',
        'display_after',
        'score_achieved',
        'nps',
        'placement',
        'num_participants',
        'event_tier',
        'k_effective',
        'is_manual_override',
        'is_suspicious',
        'suspicious_reason',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'mu_before' => 'float',
            'mu_after' => 'float',
            'phi_before' => 'float',
            'phi_after' => 'float',
            'sigma_before' => 'float',
            'sigma_after' => 'float',
            'display_before' => 'float',
            'display_after' => 'float',
            'nps' => 'float',
            'k_effective' => 'float',
            'is_manual_override' => 'boolean',
            'is_calibration' => 'boolean',
            'is_suspicious' => 'boolean',
            'event_tier' => EventTier::class,
            'computed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Rating, $this> */
    public function rating(): BelongsTo
    {
        return $this->belongsTo(Rating::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<EventDivision, $this> */
    public function eventDivision(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class);
    }

    /** @return BelongsTo<RatingPeriod, $this> */
    public function ratingPeriod(): BelongsTo
    {
        return $this->belongsTo(RatingPeriod::class);
    }
}
