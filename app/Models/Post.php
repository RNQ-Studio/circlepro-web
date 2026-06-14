<?php

namespace App\Models;

use App\Support\Enums\PostVisibility;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $author_id
 * @property string|null $organization_id
 * @property string|null $body
 * @property PostVisibility $visibility
 * @property string|null $shared_type
 * @property string|null $shared_id
 * @property int $like_count
 * @property int $comment_count
 * @property bool $is_pinned
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'author_id',
        'organization_id',
        'body',
        'visibility',
        'shared_type',
        'shared_id',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => PostVisibility::class,
            'is_pinned' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<PostLike, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('position');
    }

    /** @return HasOne<Poll, $this> */
    public function poll(): HasOne
    {
        return $this->hasOne(Poll::class);
    }

    /** @param Builder<Post> $query */
    public function scopeVisibleToFeed(Builder $query): void
    {
        $query->whereIn('visibility', [PostVisibility::Public->value, PostVisibility::Club->value]);
    }
}
