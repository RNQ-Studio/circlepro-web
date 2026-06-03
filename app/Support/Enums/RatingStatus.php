<?php

namespace App\Support\Enums;

/**
 * Classifications of user rating status based on active history.
 */
enum RatingStatus: string
{
    case Provisional = 'provisional';
    case Ranked = 'ranked';
    case Established = 'established';
    case Inactive = 'inactive';
}
