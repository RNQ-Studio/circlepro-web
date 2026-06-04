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
                'image_path' => 'https://storage.googleapis.com/manahpro-document/production/target-face/2026/06/8c688239-ffb7-42c7-a608-705dcb2c8597.jfif',
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
                'image_path' => 'https://storage.googleapis.com/manahpro-document/production/target-face/2026/06/8c688239-ffb7-42c7-a608-705dcb2c8597.jfif',
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
        ];

        $usedCounts = [
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
        ];

        foreach ($targets as $t) {
            $code = $t['code'];
            $usedCount = $usedCounts[$code] ?? 0;

            $imagePath = $t['image_path'];
            if ($imagePath && ! str_starts_with($imagePath, 'http')) {
                if (app()->runningUnitTests()) {
                    $imagePath = 'http://localhost/storage/testing/' . basename($imagePath);
                } else {
                    $uploadedUrl = $this->uploadLocalFile($imagePath);
                    if ($uploadedUrl) {
                        $imagePath = $uploadedUrl;
                    }
                }
            }

            $existing = TargetFace::where('code', $code)->first();
            if ($existing) {
                $existing->update([
                    'name' => $t['name'],
                    'image_path' => $imagePath,
                    'scoring_rules' => $t['scoring_rules'],
                    'organization_id' => $t['organization_id'],
                    'used_count' => $usedCount,
                ]);
            } else {
                TargetFace::create([
                    'code' => $code,
                    'name' => $t['name'],
                    'image_path' => $imagePath,
                    'scoring_rules' => $t['scoring_rules'],
                    'organization_id' => $t['organization_id'],
                    'used_count' => $usedCount,
                ]);
            }
        }
    }

    private function uploadLocalFile(string $localPath): ?string
    {
        $fullPath = public_path($localPath);
        if (! file_exists($fullPath)) {
            return null;
        }

        $filename = basename($fullPath);
        $existingAsset = \App\Models\Asset::where('original_filename', $filename)
            ->where('status', \App\Support\Enums\AssetStatus::Active)
            ->first();

        if ($existingAsset) {
            return $existingAsset->url;
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';
        $file = new \Illuminate\Http\UploadedFile(
            path: $fullPath,
            originalName: $filename,
            mimeType: $mime,
            error: null,
            test: true
        );

        $asset = app(\App\Services\AssetUploadService::class)->upload(
            file: $file,
            type: 'target_face',
            userId: null
        );

        return $asset->url;
    }
}
