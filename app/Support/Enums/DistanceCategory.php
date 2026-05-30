<?php

namespace App\Support\Enums;

/**
 * Standard shooting distances. Value is the canonical label (e.g. "70m").
 */
enum DistanceCategory: string
{
    case D5m = '5m';
    case D10m = '10m';
    case D15m = '15m';
    case D20m = '20m';
    case D25m = '25m';
    case D30m = '30m';
    case D40m = '40m';
    case D50m = '50m';
    case D70m = '70m';
    case D90m = '90m';
}
