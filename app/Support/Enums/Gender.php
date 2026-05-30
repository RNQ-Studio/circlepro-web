<?php

namespace App\Support\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Mixed = 'mixed'; // team events only
}
