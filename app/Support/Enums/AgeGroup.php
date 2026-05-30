<?php

namespace App\Support\Enums;

/**
 * Age group buckets (cached from birth_date for ranking filters).
 */
enum AgeGroup: string
{
    case Tk = 'tk';          // <6
    case Sd123 = 'sd_123';   // 6-9
    case Sd456 = 'sd_456';   // 9-12
    case Smp = 'smp';        // 12-15
    case Sma = 'sma';        // 15-18
    case Dewasa = 'dewasa';  // >18
}
