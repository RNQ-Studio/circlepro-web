<?php

namespace App\Models;

use App\Support\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $club_schedule_id
 * @property int $user_id
 * @property AttendanceStatus $status
 * @property string|null $remark
 * @property int|null $marked_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClubAttendance extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'club_schedule_id',
        'user_id',
        'status',
        'remark',
        'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
        ];
    }

    /** @return BelongsTo<ClubSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClubSchedule::class, 'club_schedule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
