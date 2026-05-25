<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $text
 * @property string $author
 * @property string|null $source
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['text', 'author', 'source', 'is_active'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Search quotes by text or author (case-insensitive via PostgreSQL ILIKE).
     *
     * @param  Builder<Quote>  $query
     * @return Builder<Quote>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term): void {
            $query->where('text', 'ilike', "%{$term}%")
                ->orWhere('author', 'ilike', "%{$term}%");
        });
    }
}
