<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('memory_limit', '512M');

        $statesPath = storage_path('app/regions/dr5hn/states.json');
        $provincesPath = storage_path('app/regions/emsifa/provinces.json');

        if (! file_exists($statesPath)) {
            $this->command->error('states.json not found. Run: php artisan regions:download');

            return;
        }

        if (! file_exists($provincesPath)) {
            $this->command->error('emsifa/provinces.json not found. Run: php artisan regions:download');

            return;
        }

        // Map ISO2 to region.id for all countries
        $countryMap = Region::countries()->pluck('id', 'code')->toArray();

        $indonesiaId = $countryMap['ID'] ?? null;

        if ($indonesiaId === null) {
            $this->command->error('Indonesia (ID) not found in regions table. Run CountrySeeder first.');

            return;
        }

        $now = now()->toDateTimeString();

        // --- Non-Indonesia states from dr5hn ---
        $states = json_decode(file_get_contents($statesPath), true);
        $drRows = [];

        foreach ($states as $s) {
            if (($s['country_code'] ?? '') === 'ID') {
                continue;
            }

            $parentId = $countryMap[$s['country_code'] ?? ''] ?? null;

            if ($parentId === null) {
                continue;
            }

            $drRows[] = [
                'parent_id' => $parentId,
                'type' => 'state',
                'code' => $s['state_code'] ?? null,
                'name' => $s['name'],
                'phone_code' => null,
                // source_id used by CitySeeder to resolve parent_id without extra query
                'meta' => json_encode([
                    'source_id' => $s['id'],
                    'latitude' => $s['latitude'] ?? null,
                    'longitude' => $s['longitude'] ?? null,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($drRows, 500) as $chunk) {
            DB::table('regions')->insertOrIgnore($chunk);
        }

        $this->command->info('  Non-ID states seeded: '.count($drRows));

        // --- Indonesian provinces from emsifa ---
        $provinces = json_decode(file_get_contents($provincesPath), true);
        $idRows = [];

        foreach ($provinces as $p) {
            $idRows[] = [
                'parent_id' => $indonesiaId,
                'type' => 'state',
                'code' => $p['id'],  // BPS province code e.g. "11"
                'name' => $p['name'],
                'phone_code' => null,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($idRows, 100) as $chunk) {
            DB::table('regions')->insertOrIgnore($chunk);
        }

        $this->command->info('  Indonesia provinces seeded: '.count($idRows));
    }
}
