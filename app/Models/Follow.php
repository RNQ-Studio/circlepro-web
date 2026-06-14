<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $follower_id
 * @property int $followee_id
 * @property Carbon|null $created_at
 */
class Follow extends Pivot
{
    use HasUlids;

    protected $table = 'follows';

    public $timestamps = false;

    protected $fillable = ['follower_id', 'followee_id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            $model->created_at = $model->created_at ?? now();
        });
    }

    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }

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
