<?php

namespace App\Models;

use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property BowClass $bow_class
 * @property DistanceCategory $distance_category
 * @property int $num_arrows
 * @property int $best_score
 * @property string|null $scoring_session_id
 * @property Carbon $achieved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PersonalBest extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'user_id',
        'bow_class',
        'distance_category',
        'num_arrows',
        'best_score',
        'scoring_session_id',
        'achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'bow_class' => BowClass::class,
            'distance_category' => DistanceCategory::class,
            'achieved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
