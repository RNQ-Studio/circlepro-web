<?php

namespace App\Support\Enums;

enum AppConfigType: string
{
    case String = 'string';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Json = 'json';
}
