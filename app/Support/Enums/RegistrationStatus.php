<?php

namespace App\Support\Enums;

/**
 * Event registration statuses.
 */
enum RegistrationStatus: string
{
    case Pending = 'pending';
    case Waitlisted = 'waitlisted';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
}
