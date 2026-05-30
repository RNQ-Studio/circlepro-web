<?php

namespace App\Support\Enums;

enum PostVisibility: string
{
    case Public = 'public';
    case Followers = 'followers';
    case Club = 'club';
    case Private = 'private';
}
