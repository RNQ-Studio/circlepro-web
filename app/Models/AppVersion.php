<?php

namespace App\Models;

use App\Support\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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
