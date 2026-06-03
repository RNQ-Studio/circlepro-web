<?php

namespace App\Support\Enums;

/**
 * Status of monthly rating processing period.
 */
enum RatingPeriodStatus: string
{
    case Open = 'open';
    case Computing = 'computing';
    case Closed = 'closed';
}
