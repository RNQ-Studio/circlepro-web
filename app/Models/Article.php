<?php

namespace App\Models;

use App\Support\Enums\ArticleStatus;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int|null $category_id
 * @property int $author_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $content
 * @property string|null $featured_image
 * @property ArticleStatus $status
 * @property Carbon|null $published_at
 * @property int $reading_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'category_id',
    'author_id',
    'title',
    'slug',
    'excerpt',
    'content',
    'featured_image',
    'status',
    'published_at',
    'reading_time',
    'is_islamic',
    'hadith_reference',
])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
            'reading_time' => 'integer',
            'is_islamic' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Article $article) {
            // Kalkulasi reading time otomatis: rata-rata 200 kata per menit
            if (filled($article->content)) {
                $wordCount = str_word_count(strip_tags((string) $article->content));
                $article->reading_time = (int) ceil($wordCount / 200);
            } else {
                $article->reading_time = 0;
            }

            // Set otomatis tanggal terbit jika status dipindahkan ke Published
            if ($article->status === ArticleStatus::Published && is_null($article->published_at)) {
                $article->published_at = now();
            }
        });
    }

    // ── Relations ──────────────────────────────────────────────────────

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
