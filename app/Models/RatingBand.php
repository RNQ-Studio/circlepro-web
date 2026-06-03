<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $organization_id
 * @property string $title
 * @property string|null $badge
 * @property string|null $color
 * @property int $min_display_rating
 * @property int|null $max_display_rating
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RatingBand extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'title',
        'badge',
        'color',
        'min_display_rating',
        'max_display_rating',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_display_rating' => 'integer',
            'max_display_rating' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
