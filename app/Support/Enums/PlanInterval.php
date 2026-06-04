<?php

namespace App\Support\Enums;

enum PlanInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';
}
