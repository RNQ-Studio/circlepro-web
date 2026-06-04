<?php

namespace App\Support\Enums;

enum PaymentProvider: string
{
    case GooglePlay = 'google_play';
    case AppleIAP = 'apple_iap';
    case Midtrans = 'midtrans';
    case Manual = 'manual';
}
