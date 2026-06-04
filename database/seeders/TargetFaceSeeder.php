<?php

namespace Database\Seeders;

use App\Models\TargetFace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TargetFaceSeeder extends Seeder
{
    public function run(): void
    {
        $fitaRules = [
            ['value' => 10, 'label' => 'X', 'color' => '#FFC107', 'is_x' => true],
            ['value' => 10, 'label' => '10', 'color' => '#FFC107'],
            ['value' => 9, 'label' => '9', 'color' => '#FFC107'],
            ['value' => 8, 'label' => '8', 'color' => '#E53935'],
            ['value' => 7, 'label' => '7', 'color' => '#E53935'],
            ['value' => 6, 'label' => '6', 'color' => '#1E88E5'],
            ['value' => 5, 'label' => '5', 'color' => '#1E88E5'],
            ['value' => 4, 'label' => '4', 'color' => '#1A1A1A'],
            ['value' => 3, 'label' => '3', 'color' => '#1A1A1A'],
            ['value' => 2, 'label' => '2', 'color' => '#CCCCCC'],
            ['value' => 1, 'label' => '1', 'color' => '#CCCCCC'],
            ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
        ];

        $targets = [
            [
                'code' => 'fita_122',
                'name' => 'FITA / WA (122cm)',
                'image_path' => 'assets/images/targets/target_fita_10_ring.png',
                'scoring_rules' => $fitaRules,
            ],
            [
                'code' => 'fita_80',
                'name' => 'FITA / WA (80cm)',
                'image_path' => 'assets/images/targets/target_fita_10_ring.png',
                'scoring_rules' => $fitaRules,
            ],
            [
                'code' => 'fita_60',
                'name' => 'FITA / WA (60cm)',
                'image_path' => 'assets/images/targets/target_fita_10_ring.png',
                'scoring_rules' => $fitaRules,
            ],
            [
                'code' => 'fita_40',
                'name' => 'FITA / WA (40cm)',
                'image_path' => 'assets/images/targets/target_fita_10_ring.png',
                'scoring_rules' => $fitaRules,
            ],
            [
                'code' => 'jemparingan',
                'name' => 'Jemparingan (Bandul)',
                'image_path' => 'assets/images/targets/target_jemparingan.png',
                'scoring_rules' => [
                    ['value' => 3, 'label' => 'Sirah (3)', 'color' => '#E53935'],
                    ['value' => 1, 'label' => 'Awak (1)', 'color' => '#FAFAFA'],
                    ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
                ],
            ],
            [
                'code' => 'las_vegas_3spot',
                'name' => 'Las Vegas 3-Spot',
                'image_path' => 'assets/images/targets/target_las_vegas_3spot.png',
                'scoring_rules' => [
                    ['value' => 10, 'label' => 'X', 'color' => '#FFC107', 'is_x' => true],
                    ['value' => 10, 'label' => '10', 'color' => '#FFC107'],
                    ['value' => 9, 'label' => '9', 'color' => '#FFC107'],
                    ['value' => 8, 'label' => '8', 'color' => '#E53935'],
                    ['value' => 7, 'label' => '7', 'color' => '#E53935'],
                    ['value' => 6, 'label' => '6', 'color' => '#1E88E5'],
                    ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
                ],
            ],
        ];

        foreach ($targets as $t) {
            TargetFace::query()->updateOrCreate(
                ['code' => $t['code']],
                [
                    'id' => (string) Str::ulid(),
                    'name' => $t['name'],
                    'image_path' => $t['image_path'],
                    'scoring_rules' => $t['scoring_rules'],
                ]
            );
        }
    }
}
