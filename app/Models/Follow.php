<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $follower_id
 * @property int $followee_id
 * @property Carbon|null $created_at
 */
class Follow extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['follower_id', 'followee_id'];

    /** @return BelongsTo<User, $this> */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /** @return BelongsTo<User, $this> */
    public function followee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followee_id');
    }
}
