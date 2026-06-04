<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'subscription_plan_id',
        'subscriber_type',
        'user_id',
        'organization_id',
        'status',
        'provider',
        'provider_subscription_id',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'grace_ends_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<SubscriptionInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isActive(): bool
    {
        if (in_array($this->status, ['active', 'trialing'])) {
            return true;
        }

        if ($this->status === 'cancelled' && $this->current_period_end && $this->current_period_end->isFuture()) {
            return true;
        }

        if ($this->status === 'past_due' && $this->grace_ends_at && $this->grace_ends_at->isFuture()) {
            return true;
        }

        return false;
    }
}
