<?php

namespace App\Support\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Unpaid = 'unpaid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Trialing = 'trialing';
}
