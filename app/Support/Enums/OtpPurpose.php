<?php

namespace App\Support\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
    case Register = 'register';
    case VerifyPhone = 'verify_phone';
    case ResetPassword = 'reset_password';
}
