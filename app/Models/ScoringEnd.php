<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $scoring_session_id
 * @property int $end_number
 * @property int $end_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ScoringArrow> $arrows
 */
class ScoringEnd extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'scoring_session_id',
        'end_number',
        'end_total',
    ];

    /** @return BelongsTo<ScoringSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ScoringSession::class, 'scoring_session_id');
    }

    /** @return HasMany<ScoringArrow, $this> */
    public function arrows(): HasMany
    {
        return $this->hasMany(ScoringArrow::class)->orderBy('arrow_index');
    }
}
