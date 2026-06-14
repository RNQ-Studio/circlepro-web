<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\TargetFace;
use App\Services\AssetUploadService;
use App\Support\Enums\AssetStatus;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class UploadMasterTargets extends Command
{
    protected $signature = 'circlepro:upload-master-images';

    protected $description = 'Upload local target images to GCS and print their public URLs';

    public function handle(AssetUploadService $assetUploadService): int
    {
        $files = [
            'fita' => [
                'path' => 'assets/images/targets/target_fita_10_ring.png',
                'codes' => ['fita_122', 'fita_80', 'fita_60', 'fita_40'],
            ],
            'vegas' => [
                'path' => 'assets/images/targets/target_las_vegas_3spot.png',
                'codes' => ['las_vegas_3spot'],
            ],
        ];

        foreach ($files as $key => $info) {
            $localPath = $info['path'];
            $fullPath = public_path($localPath);

            if (! file_exists($fullPath)) {
                $this->error("File not found at: {$fullPath}");

                continue;
            }

            $filename = basename($fullPath);
            $this->info("Processing {$filename}...");

            // Find existing asset
            $asset = Asset::where('original_filename', $filename)
                ->where('status', AssetStatus::Active)
                ->first();

            if (! $asset) {
                $mime = mime_content_type($fullPath) ?: 'image/png';
                $uploadedFile = new UploadedFile(
                    path: $fullPath,
                    originalName: $filename,
                    mimeType: $mime,
                    error: null,
                    test: true
                );

                try {
                    $asset = $assetUploadService->upload(
                        file: $uploadedFile,
                        type: 'target_face',
                        userId: null
                    );
                    $this->info("Successfully uploaded {$filename} to GCS.");
                } catch (\Throwable $e) {
                    $this->error("Failed to upload {$filename}: {$e->getMessage()}");

                    continue;
                }
            } else {
                $this->comment("Asset {$filename} already exists in database. Using existing URL.");
            }

            $this->line("GCS URL for {$key}: {$asset->url}");

            // Update database records
            foreach ($info['codes'] as $code) {
                $target = TargetFace::where('code', $code)->first();
                if ($target) {
                    $target->update(['image_path' => $asset->url]);
                    $this->info("Updated target face [{$code}] image_path to: {$asset->url}");
                } else {
                    $this->warn("Target face [{$code}] not found in database.");
                }
            }
        }

        $this->info('Done!');

        return self::SUCCESS;
    }
}
