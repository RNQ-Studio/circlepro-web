<?php

namespace App\Support\Enums;

/**
 * Tenant anchor types. `platform` is the root ManahPro tenant (national/global data).
 *
 * @see apps/manahpro/docs/database-design.md §2 (Model Tenancy)
 */
enum OrganizationType: string
{
    case Platform = 'platform';
    case Club = 'club';
    case Association = 'association';
    case EventOrganizer = 'event_organizer';
    case Shop = 'shop';
    case Partner = 'partner';
}
