<?php

namespace App\Models;

use App\Support\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_division_id
 * @property int $user_id
 * @property RegistrationStatus $status
 * @property string|null $payment_id
 * @property string|null $bib_number
 * @property string|null $qr_code
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EventDivision $division
 * @property-read User $user
 */
class EventRegistration extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'event_division_id',
        'user_id',
        'status',
        'payment_id',
        'bib_number',
        'qr_code',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'checked_in_at' => 'datetime',
            'user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<EventDivision, $this> */
    public function division(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'event_division_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
