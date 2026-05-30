<?php

namespace App\Models;

use App\Support\Enums\VerificationStatus;
use App\Support\Enums\VerificationType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property VerificationType $type
 * @property VerificationStatus $status
 * @property string|null $document_url
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserVerification extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'document_url',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => VerificationType::class,
            'status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
