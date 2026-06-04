<?php

namespace App\Support\Enums;

enum PlanAudience: string
{
    case User = 'user';
    case Organization = 'organization';
}
