<?php

namespace App\Support\Enums;

/**
 * Event workflow statuses.
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case Live = 'live';
    case Completed = 'completed';
    case Rated = 'rated';
    case Cancelled = 'cancelled';
}
