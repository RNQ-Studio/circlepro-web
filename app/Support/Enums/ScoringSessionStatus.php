<?php

namespace App\Support\Enums;

enum ScoringSessionStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
}
