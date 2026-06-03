<?php

namespace App\Support\Enums;

/**
 * Event tier classifications for rating calculation.
 */
enum EventTier: string
{
    case S = 'S'; // National / PON / SEA Games Qualifier
    case A = 'A'; // Kejurda / Regional / National Series
    case B = 'B'; // Kota / Club Open
    case C = 'C'; // Friendly/Inter-club sparring
    case D = 'D'; // Internal / Club practice
}
