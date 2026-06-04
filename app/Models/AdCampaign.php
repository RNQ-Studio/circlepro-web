<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'advertiser_org_id',
        'user_id',
        'payment_id',
        'name',
        'budget',
        'starts_at',
        'ends_at',
        'status',
        'targeting',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'targeting' => 'array',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function advertiserOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'advertiser_org_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return HasMany<Ad, $this> */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
