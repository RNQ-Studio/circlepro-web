<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $scoring_end_id
 * @property int $arrow_index
 * @property int $score_value
 * @property bool $is_x
 * @property bool $is_miss
 * @property float|null $pos_x
 * @property float|null $pos_y
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ScoringArrow extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'scoring_end_id',
        'arrow_index',
        'score_value',
        'is_x',
        'is_miss',
        'pos_x',
        'pos_y',
    ];

    protected function casts(): array
    {
        return [
            'is_x' => 'boolean',
            'is_miss' => 'boolean',
            'pos_x' => 'float',
            'pos_y' => 'float',
        ];
    }

    /** @return BelongsTo<ScoringEnd, $this> */
    public function end(): BelongsTo
    {
        return $this->belongsTo(ScoringEnd::class, 'scoring_end_id');
    }
}
