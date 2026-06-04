<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Services\AssetDeletionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class CleanExpiredStories extends Command
{
    protected $signature = 'stories:clean-expired {--chunk=100}';

    protected $description = 'Hapus story yang sudah melewati batas waktu 24 jam dan hapus file fisiknya di GCS';

    public function handle(AssetDeletionService $assetDeletionService): int
    {
        $chunk = (int) $this->option('chunk');
        $processed = 0;

        Story::query()
            ->where('expires_at', '<=', now())
            ->chunkById($chunk, function (Collection $stories) use ($assetDeletionService, &$processed): void {
                /** @var Collection<int, Story> $stories */
                foreach ($stories as $story) {
                    if ($story->asset) {
                        try {
                            $assetDeletionService->hardDelete($story->asset);
                        } catch (\Throwable $e) {
                            $this->error("Gagal menghapus asset story {$story->id}: {$e->getMessage()}");
                            Log::error('stories:clean-expired asset deletion failed', [
                                'story_id' => $story->id,
                                'asset_id' => $story->asset_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    $story->delete();
                    $processed++;
                }
            });

        $this->info("Successfully cleaned {$processed} expired story/stories.");
        Log::info('stories:clean-expired completed', ['processed' => $processed]);

        return self::SUCCESS;
    }
}
