<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Support\Enums\OrganizationType;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $orgs = [
            [
                'slug' => 'perpani',
                'name' => 'Persatuan Panahan Indonesia (PERPANI)',
                'description' => 'Induk organisasi olahraga panahan prestasi di Indonesia di bawah naungan KONI.',
            ],
            [
                'slug' => 'perpatri',
                'name' => 'Persatuan Panah Tradisional Indonesia (PERPATRI)',
                'description' => 'Organisasi panahan tradisional di Indonesia yang melestarikan seni panahan adat Nusantara.',
            ],
            [
                'slug' => 'fespati',
                'name' => 'Federasi Seni Panahan Tradisional Indonesia (FESPATI)',
                'description' => 'Federasi yang mewadahi olahraga panahan tradisional di Indonesia di bawah naungan KORMI.',
            ],
            [
                'slug' => 'pordasi',
                'name' => 'Persatuan Olahraga Berkuda Seluruh Indonesia (PORDASI)',
                'description' => 'Induk organisasi olahraga berkuda nasional, menaungi juga cabang panahan berkuda (horseback archery).',
            ],
            [
                'slug' => 'kpbi',
                'name' => 'Perkumpulan Panahan Berkuda Indonesia (KPBI)',
                'description' => 'Organisasi yang berfokus pada pengembangan seni panahan berkuda dan pelestarian teknik panahan tradisional.',
            ],
            [
                'slug' => 'okcular-vakfi',
                'name' => 'Okçular Vakfı (Archers Foundation)',
                'description' => 'Yayasan pelestari budaya dan olahraga panahan tradisional serta modern di Turki, penyelenggara utama Fetih Kupası.',
            ],
        ];

        foreach ($orgs as $org) {
            Organization::query()->updateOrCreate(
                ['slug' => $org['slug']],
                [
                    'type' => OrganizationType::Association,
                    'name' => $org['name'],
                    'description' => $org['description'],
                    'is_verified' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
