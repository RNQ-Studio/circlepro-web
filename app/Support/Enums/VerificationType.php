<?php

namespace App\Support\Enums;

enum VerificationType: string
{
    case Phone = 'phone';
    case Ktp = 'ktp';
    case Perpani = 'perpani';
    case ShopLicense = 'shop_license';
}
