<?php

namespace App\Support\Enums;

/**
 * Role of a user within an organization (organization_members.role).
 */
enum MemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Coach = 'coach';
    case Scorer = 'scorer';
    case Member = 'member';
}
