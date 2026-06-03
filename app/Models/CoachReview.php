<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $coach_profile_id
 * @property int $user_id
 * @property int $rating
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CoachReview extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'coach_profile_id',
        'user_id',
        'rating',
        'comment',
    ];

    /** @return BelongsTo<CoachProfile, $this> */
    public function coachProfile(): BelongsTo
    {
        return $this->belongsTo(CoachProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
