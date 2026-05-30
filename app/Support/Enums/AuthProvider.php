<?php

namespace App\Support\Enums;

/**
 * Social/identity providers linked to a user (user_auth_providers.provider).
 */
enum AuthProvider: string
{
    case Google = 'google';
    case Apple = 'apple';
    case Phone = 'phone';
    case Email = 'email';
}
