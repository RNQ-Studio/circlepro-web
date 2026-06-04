<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'payment_number',
        'user_id',
        'payable_type',
        'payable_id',
        'provider',
        'method',
        'amount',
        'fee',
        'currency',
        'status',
        'provider_ref',
        'paid_at',
        'expired_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee' => 'integer',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
