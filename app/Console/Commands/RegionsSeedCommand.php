<?php

namespace App\Console\Commands;

use Database\Seeders\RegionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegionsSeedCommand extends Command
{
    protected $signature = 'regions:seed {--fresh : Truncate the regions table before seeding}';

    protected $description = 'Seed the regions table (countries, states, cities, districts, villages)';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        if (! $this->dataFilesExist()) {
            $this->error('Source data files not found. Run: php artisan regions:download');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('Truncating regions table...');
            DB::statement('TRUNCATE TABLE regions RESTART IDENTITY CASCADE');
        }

        $this->info('Seeding regions - this may take a few minutes...');
        $start = microtime(true);

        $seeder = new RegionSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $elapsed = round(microtime(true) - $start, 1);
        $total = DB::table('regions')->count();

        $this->info("Done in {$elapsed}s - {$total} regions total.");

        return self::SUCCESS;
    }

    private function dataFilesExist(): bool
    {
        $required = [
            storage_path('app/regions/dr5hn/countries.json'),
            storage_path('app/regions/dr5hn/states.json'),
            storage_path('app/regions/dr5hn/cities.json'),
            storage_path('app/regions/emsifa/provinces.json'),
            storage_path('app/regions/emsifa/regencies.json'),
            storage_path('app/regions/emsifa/districts.json'),
            storage_path('app/regions/emsifa/villages.json'),
        ];

        foreach ($required as $file) {
            if (! file_exists($file)) {
                return false;
            }
        }

        return true;
    }
}
