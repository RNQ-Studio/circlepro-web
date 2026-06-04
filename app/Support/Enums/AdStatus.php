<?php

namespace App\Support\Enums;

enum AdStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
}
