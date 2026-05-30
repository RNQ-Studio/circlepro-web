<?php

namespace App\Support\Enums;

/**
 * Global platform role on the user record (users.system_role).
 * Distinct from Spatie back-office roles used by Filament.
 */
enum SystemRole: string
{
    case User = 'user';
    case Staff = 'staff';
    case Superadmin = 'superadmin';
}
