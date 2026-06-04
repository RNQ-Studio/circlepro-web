<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\TargetFace;
use Illuminate\Database\Seeder;

class TargetFaceSeeder extends Seeder
{
    public function run(): void
    {
        $perpani = Organization::where('slug', 'perpani')->first();
        $fespati = Organization::where('slug', 'fespati')->first();
        $perpatri = Organization::where('slug', 'perpatri')->first();
        $pordasi = Organization::where('slug', 'pordasi')->first();
        $kpbi = Organization::where('slug', 'kpbi')->first();
        $okcularVakfi = Organization::where('slug', 'okcular-vakfi')->first();

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

        $sixRingRules = [
            ['value' => 10, 'label' => 'X', 'color' => '#FFC107', 'is_x' => true],
            ['value' => 10, 'label' => '10', 'color' => '#FFC107'],
            ['value' => 9, 'label' => '9', 'color' => '#FFC107'],
            ['value' => 8, 'label' => '8', 'color' => '#E53935'],
            ['value' => 7, 'label' => '7', 'color' => '#E53935'],
            ['value' => 6, 'label' => '6', 'color' => '#1E88E5'],
            ['value' => 5, 'label' => '5', 'color' => '#1E88E5'],
            ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
        ];

        $fiveRingRules = [
            ['value' => 5, 'label' => '5', 'color' => '#FFC107'],
            ['value' => 4, 'label' => '4', 'color' => '#E53935'],
            ['value' => 3, 'label' => '3', 'color' => '#1E88E5'],
            ['value' => 2, 'label' => '2', 'color' => '#1A1A1A'],
            ['value' => 1, 'label' => '1', 'color' => '#CCCCCC'],
            ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
        ];

        $twoRingRules = [
            ['value' => 2, 'label' => '2', 'color' => '#E53935'],
            ['value' => 1, 'label' => '1', 'color' => '#FAFAFA'],
            ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
        ];

        $targets = [
            // Original 6 Targets
            [
                'code' => 'fita_122',
                'name' => 'FITA / WA (122cm)',
                'image_path' => 'assets/images/targets/target_fita_10_ring.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $perpani?->id,
            ],
            [
                'code' => 'fita_80',
                'name' => 'FITA / WA (80cm)',
                'image_path' => 'assets/images/targets/target_fita_10_ring.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $perpani?->id,
            ],
            [
                'code' => 'fita_60',
                'name' => 'FITA / WA (60cm)',
                'image_path' => 'assets/images/targets/target_fita_10_ring.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $perpani?->id,
            ],
            [
                'code' => 'fita_40',
                'name' => 'FITA / WA (40cm)',
                'image_path' => 'assets/images/targets/target_fita_10_ring.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $perpani?->id,
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
                'organization_id' => $perpatri?->id,
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
                'organization_id' => $perpani?->id,
            ],

            // 20 Targets from API
            [
                'code' => 'perpatri_10ring',
                'name' => '10 RING - PERPATRI',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face_target/12097430-41c7-4abf-905c-d75b2e999646.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $perpatri?->id,
            ],
            [
                'code' => '6ring_fespati',
                'name' => '6 RING - FESPATI',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face%20target/6607771b-c95f-41a6-9a34-d06bea8d5f56.png',
                'scoring_rules' => $sixRingRules,
                'organization_id' => $fespati?->id,
            ],
            [
                'code' => 'fetih_kupasi',
                'name' => 'FETIH KUPASI',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face%20target/897f3f13-0849-4f7d-8cf1-2c15978fc280.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $okcularVakfi?->id,
            ],
            [
                'code' => 'perpatri_nj_horsebow',
                'name' => 'PERPATRI NJ - HORSEBOW',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face_target/6FsKhoLkrkaviYNqipfaVwOW0QQhSYdjTiHUZEZk.jpg',
                'scoring_rules' => $fitaRules,
                'organization_id' => $perpatri?->id,
            ],
            [
                'code' => '6ring_80cm_perpani',
                'name' => '6 RING 80cm - PERPANI',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face_target/rc0WSnwWHdgqOBilaW3JEtbaXhzzNwpDs6GDUYnk.jpg',
                'scoring_rules' => $sixRingRules,
                'organization_id' => $perpani?->id,
            ],
            [
                'code' => 'megamendung_fespati',
                'name' => 'MEGA MENDUNG - FESPATI',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face%20target/1d1d5e28-750b-4ce7-8757-044afcfae2cb.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $fespati?->id,
            ],
            [
                'code' => 'bandul_merahkuningputih',
                'name' => 'BANDUL MERAH KUNING PUTIH',
                'image_path' => 'https://manah.umrah.pro/uploads/face_target/c4278fa0-dc2f-476e-966b-1d55afeed719.jpg',
                'scoring_rules' => [
                    ['value' => 3, 'label' => 'Merah (3)', 'color' => '#E53935'],
                    ['value' => 2, 'label' => 'Kuning (2)', 'color' => '#FFC107'],
                    ['value' => 1, 'label' => 'Putih (1)', 'color' => '#FAFAFA'],
                    ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
                ],
                'organization_id' => $perpatri?->id,
            ],
            [
                'code' => 'pordasi_hexagon4',
                'name' => 'HEXAGON - PORDASI ON GROUND',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face%20target/5523dd5d-16c1-4851-8456-7f0c25508fe7.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $pordasi?->id,
            ],
            [
                'code' => 'bandul_merahputih',
                'name' => 'BANDUL MERAH PUTIH',
                'image_path' => 'https://manah.umrah.pro/uploads/face_target/c4278fa0-dc2f-476e-966b-1d55afeed719.jpg',
                'scoring_rules' => [
                    ['value' => 3, 'label' => 'Merah (3)', 'color' => '#E53935'],
                    ['value' => 1, 'label' => 'Putih (1)', 'color' => '#FAFAFA'],
                    ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
                ],
                'organization_id' => $perpatri?->id,
            ],
            [
                'code' => 'laga_muharram_temboro',
                'name' => 'Face Target Laga Muharram Temboro',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face_target/jkdEVHUMyWGLwhX86VHFQgpNhXuATNTTgk71lD1R.jpg',
                'scoring_rules' => [
                    ['value' => 3, 'label' => 'Merah (3)', 'color' => '#E53935'],
                    ['value' => 2, 'label' => 'Hijau (2)', 'color' => '#4CAF50'],
                    ['value' => 1, 'label' => 'Kuning (1)', 'color' => '#FFC107'],
                    ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
                ],
                'organization_id' => null,
            ],
            [
                'code' => 'pordasi_purwakarta_anak2',
                'name' => 'PORDASI PURWAKARTA - ANAK2',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face_target/0rsiWxaK9JOkg1WD0XgmtC1mtD6lfk9NRg56Qgx3.jpg',
                'scoring_rules' => $fiveRingRules,
                'organization_id' => $pordasi?->id,
            ],
            [
                'code' => 'pordasi_60x60',
                'name' => 'Pordasi 60 x 60',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face_target/TjEPyuQbVBhq8ljAsU4hO7WeRe9cprTSms5e1ccu.jpg',
                'scoring_rules' => $fitaRules,
                'organization_id' => $pordasi?->id,
            ],
            [
                'code' => 'pordasi_hexagon_horse',
                'name' => 'HEXAGON - PORDASI ON HORSE',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face%20target/5523dd5d-16c1-4851-8456-7f0c25508fe7.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $pordasi?->id,
            ],
            [
                'code' => 'pordasi_purwakarta_dewasa',
                'name' => 'PORDASI PURWAKARTA - DEWASA',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face_target/hi8KevXqyLc88SN6bH2fK7ogtGbMBXUwf5QNQBab.jpg',
                'scoring_rules' => $twoRingRules,
                'organization_id' => $pordasi?->id,
            ],
            [
                'code' => 'fast_shooting_pordasi',
                'name' => 'FAST SHOOTING - PORDASI',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face%20target/10fe5c6d-88e2-470e-b4f6-8678d60ef82c.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $pordasi?->id,
            ],
            [
                'code' => 'tameng_majapahit_kpbi',
                'name' => 'TAMENG MAJAPAHIT - KPBI',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face%20target/130b5562-e196-4e10-af0f-b0a6393df18b.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $kpbi?->id,
            ],
            [
                'code' => 'gunungan_perdana',
                'name' => 'Gunungan - PERDANA',
                'image_path' => null,
                'scoring_rules' => $fiveRingRules,
                'organization_id' => null,
            ],
            [
                'code' => 'hit_n_miss',
                'name' => 'Hit n Miss',
                'image_path' => null,
                'scoring_rules' => [
                    ['value' => 1, 'label' => 'Hit', 'color' => '#E53935'],
                    ['value' => 0, 'label' => 'M', 'color' => '#E57373', 'is_miss' => true],
                ],
                'organization_id' => null,
            ],
            [
                'code' => 'rajawali_kpbi',
                'name' => 'RAJAWALI - KPBI',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face%20target/70b282ab-5fff-4b07-b864-4221c7e420b2.jpg',
                'scoring_rules' => $fitaRules,
                'organization_id' => $kpbi?->id,
            ],
            [
                'code' => 'gonjang_ganjing_kpbi',
                'name' => 'GONJANG GANJING - KPBI',
                'image_path' => 'https://storage.googleapis.com/manahpro-document/uploads/face%20target/9ee68d4d-0a8e-405b-947f-47502afe4923.png',
                'scoring_rules' => $fitaRules,
                'organization_id' => $kpbi?->id,
            ],
        ];

        $participantCounts = [
            'perpatri_10ring' => 2275,
            '6ring_fespati' => 1400,
            'fetih_kupasi' => 593,
            'perpatri_nj_horsebow' => 582,
            '6ring_80cm_perpani' => 246,
            'megamendung_fespati' => 232,
            'bandul_merahkuningputih' => 197,
            'pordasi_hexagon4' => 132,
            'bandul_merahputih' => 89,
            'laga_muharram_temboro' => 80,
            'pordasi_purwakarta_anak2' => 64,
            'pordasi_60x60' => 50,
            'pordasi_hexagon_horse' => 46,
            'pordasi_purwakarta_dewasa' => 34,
            'fast_shooting_pordasi' => 33,
            'tameng_majapahit_kpbi' => 24,
            'gunungan_perdana' => 23,
            'hit_n_miss' => 11,
            'rajawali_kpbi' => 10,
            'gonjang_ganjing_kpbi' => 10,
        ];

        foreach ($targets as $t) {
            $code = $t['code'];
            $totalParticipants = $participantCounts[$code] ?? 0;
            $existing = TargetFace::where('code', $code)->first();
            if ($existing) {
                $existing->update([
                    'name' => $t['name'],
                    'image_path' => $t['image_path'],
                    'scoring_rules' => $t['scoring_rules'],
                    'organization_id' => $t['organization_id'],
                    'total_participants' => $totalParticipants,
                ]);
            } else {
                TargetFace::create([
                    'code' => $code,
                    'name' => $t['name'],
                    'image_path' => $t['image_path'],
                    'scoring_rules' => $t['scoring_rules'],
                    'organization_id' => $t['organization_id'],
                    'total_participants' => $totalParticipants,
                ]);
            }
        }
    }
}
