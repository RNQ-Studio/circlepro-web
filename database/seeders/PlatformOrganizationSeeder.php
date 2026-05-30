<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Support\Enums\OrganizationType;
use Illuminate\Database\Seeder;

/**
 * Seeds the singleton platform organization (root tenant). Resources whose
 * organization_id points here represent national/global ManahPro data.
 * Idempotent — safe to re-run.
 */
class PlatformOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::query()->updateOrCreate(
            ['slug' => 'manahpro'],
            [
                'type' => OrganizationType::Platform,
                'name' => 'ManahPro',
                'description' => 'Root tenant — national/global ManahPro scope.',
                'is_verified' => true,
                'is_active' => true,
            ],
        );
    }
}
