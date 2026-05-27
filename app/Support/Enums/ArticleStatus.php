<?php

namespace App\Support\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
