<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/regions/emsifa/districts.json');

        if (! file_exists($path)) {
            $this->command->error('emsifa/districts.json not found. Run: php artisan regions:download');

            return;
        }

        $now = now()->toDateTimeString();

        // BPS regency code to region.id for all Indonesian regencies
        $regencyMap = DB::table('regions')
            ->where('type', 'city')
            ->whereNotNull('code')
            ->pluck('id', 'code')
            ->toArray();

        $districts = json_decode(file_get_contents($path), true);
        $count = 0;
        $batch = [];

        foreach ($districts as $d) {
            $parentId = $regencyMap[$d['regency_id'] ?? ''] ?? null;

            if ($parentId === null) {
                continue;
            }

            $batch[] = [
                'parent_id' => $parentId,
                'type' => 'district',
                'code' => $d['id'],  // BPS district code e.g. "1101010"
                'name' => $d['name'],
                'phone_code' => null,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                DB::table('regions')->insertOrIgnore($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('regions')->insertOrIgnore($batch);
            $count += count($batch);
        }

        $this->command->info("  Districts (kecamatan) seeded: {$count}");
    }
}
