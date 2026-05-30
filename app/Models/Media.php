<?php

namespace App\Models;

use App\Support\Enums\MediaType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Generic polymorphic media gallery. See database-design.md §9.
 *
 * @property string $id
 * @property string $mediable_type
 * @property string $mediable_id
 * @property MediaType $type
 * @property string $url
 * @property string|null $thumbnail_url
 * @property int|null $width
 * @property int|null $height
 * @property int $position
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Media extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'media';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'type',
        'url',
        'thumbnail_url',
        'width',
        'height',
        'position',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'meta' => 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
