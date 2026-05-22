<?php

namespace App\Models;

use App\Support\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property DevicePlatform $platform
 * @property string $min_version
 * @property string $latest_version
 * @property bool $force_update
 * @property string|null $store_url
 * @property string|null $release_notes
 */
class AppVersion extends Model
{
    protected $fillable = [
        'platform',
        'min_version',
        'latest_version',
        'force_update',
        'store_url',
        'release_notes',
    ];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'force_update' => 'boolean',
        ];
    }
}
