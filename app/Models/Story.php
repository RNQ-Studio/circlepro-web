<?php

namespace App\Models;

use App\Support\Enums\MediaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $asset_id
 * @property MediaType $media_type
 * @property string $media_url
 * @property string|null $caption
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Asset|null $asset
 * @property-read Collection<int, User> $viewers
 */
class Story extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'asset_id',
        'media_type',
        'media_url',
        'caption',
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

    /** @return HasMany<StoryView, $this> */
    public function views(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'story_views', 'story_id', 'user_id')
            ->withTimestamps();
    }

    // ── Query Scopes ───────────────────────────────────────────────────

    /** @param Builder<Story> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }
}
