<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClubSchedule extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'location',
        'start_time',
        'end_time',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ClubAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(ClubAttendance::class);
    }
}
