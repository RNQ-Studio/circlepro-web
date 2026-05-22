<?php

namespace App\Models;

use App\Support\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $phone
 * @property string $code
 * @property OtpPurpose $purpose
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property string|null $ip_address
 */
class OtpCode extends Model
{
    protected $fillable = [
        'phone',
        'code',
        'purpose',
        'expires_at',
        'used_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
