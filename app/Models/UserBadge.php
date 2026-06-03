<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $badge_id
 * @property Carbon $unlocked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserBadge extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'badge_id',
        'unlocked_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'unlocked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Badge, $this> */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
