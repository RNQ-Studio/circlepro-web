<?php

namespace App\Models;

use App\Support\Enums\MediaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $asset_id
 * @property MediaType $media_type
 * @property string $media_url
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Asset|null $asset
 */
class Story extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'asset_id',
        'media_type',
        'media_url',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'media_type' => MediaType::class,
            'expires_at' => 'datetime',
        ];
    }

    // ── Relations ──────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    // ── Query Scopes ───────────────────────────────────────────────────

    /** @param Builder<Story> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }
}
