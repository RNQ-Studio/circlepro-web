<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int $xp
 * @property int $level
 * @property int $current_streak
 * @property int $longest_streak
 * @property Carbon|null $last_session_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserStat extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'xp',
        'level',
        'current_streak',
        'longest_streak',
        'last_session_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'xp' => 'integer',
            'level' => 'integer',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'last_session_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
