<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('memory_limit', '512M');

        $this->command->info('Seeding regions...');

        $this->call([
            CountrySeeder::class,
            StateSeeder::class,
            CitySeeder::class,
            DistrictSeeder::class,
            VillageSeeder::class,
        ]);

        $this->command->info('Region seeding complete.');
    }
}
