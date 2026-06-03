<?php

namespace App\Models;

use App\Support\Enums\RatingPeriodStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property Carbon $period_month
 * @property RatingPeriodStatus $status
 * @property Carbon|null $decay_applied_at
 * @property Carbon|null $computed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RatingPeriod extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'period_month',
        'status',
        'decay_applied_at',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'status' => RatingPeriodStatus::class,
            'decay_applied_at' => 'datetime',
            'computed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<RatingHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(RatingHistory::class);
    }
}
