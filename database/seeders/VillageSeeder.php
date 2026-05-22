<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VillageSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/regions/emsifa/villages.json');

        if (! file_exists($path)) {
            $this->command->error('emsifa/villages.json not found. Run: php artisan regions:download');

            return;
        }

        $now = now()->toDateTimeString();

        // BPS district code to region.id for all Indonesian districts
        $districtMap = DB::table('regions')
            ->where('type', 'district')
            ->whereNotNull('code')
            ->pluck('id', 'code')
            ->toArray();

        $count = 0;
        $batch = [];

        foreach ($this->readJsonObjects($path) as $v) {
            $parentId = $districtMap[$v['district_id'] ?? ''] ?? null;

            if ($parentId === null) {
                continue;
            }

            $batch[] = [
                'parent_id' => $parentId,
                'type' => 'village',
                'code' => $v['id'],  // BPS village code e.g. "1101010001"
                'name' => $v['name'],
                'phone_code' => null,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 1000) {
                DB::table('regions')->insertOrIgnore($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('regions')->insertOrIgnore($batch);
            $count += count($batch);
        }

        $this->command->info("  Villages (kelurahan/desa) seeded: {$count}");
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function readJsonObjects(string $path): \Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        $buffer = '';
        $depth = 0;
        $inString = false;
        $escaped = false;

        while (($char = fgetc($handle)) !== false) {
            if ($depth > 0) {
                $buffer .= $char;
            }

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $depth++;

                if ($depth === 1 && $buffer === '') {
                    $buffer = '{';
                }

                continue;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    $decoded = json_decode($buffer, true);

                    if (is_array($decoded)) {
                        yield $decoded;
                    }

                    $buffer = '';
                }
            }
        }

        fclose($handle);
    }
}
