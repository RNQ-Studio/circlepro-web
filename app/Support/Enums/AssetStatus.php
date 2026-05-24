<?php

namespace App\Support\Enums;

enum AssetStatus: string
{
    case Active = 'active';
    case SoftDeleted = 'soft_deleted';
    case HardDeleted = 'hard_deleted';
}
