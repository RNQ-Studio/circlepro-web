<?php

namespace App\Support\Enums;

/**
 * Origin of a scoring session record (offline-first sync metadata).
 */
enum SyncSource: string
{
    case Mobile = 'mobile';
    case Web = 'web';
    case Scorer = 'scorer'; // entered by a scorer during an event
    case Import = 'import';
}
