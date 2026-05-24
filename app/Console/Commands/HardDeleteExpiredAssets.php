<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Support\Enums\AssetStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HardDeleteExpiredAssets extends Command
{
    protected $signature = 'assets:hard-delete-expired {--chunk=500}';

    protected $description = 'Hapus permanen file asset dari storage & tandai hard_deleted setelah jadwal tiba';

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk');
        $success = 0;
        $failed = 0;

        // scopePendingHardDelete: status=soft_deleted & scheduled_hard_delete_at sudah lewat.
        // chunkById aman karena record keluar dari window setelah status berubah jadi hard_deleted.
        Asset::query()
            ->pendingHardDelete()
            ->chunkById($chunk, function (Collection $assets) use (&$success, &$failed): void {
                /** @var Collection<int, Asset> $assets */
                foreach ($assets as $asset) {
                    try {
                        $disk = Storage::disk($asset->storage_type->disk());

                        // Idempoten: kalau file sudah tidak ada, anggap berhasil dan lanjut tandai.
                        if ($disk->exists($asset->path)) {
                            $disk->delete($asset->path);
                        }

                        $asset->status = AssetStatus::HardDeleted;
                        $asset->hard_deleted_at = now();
                        $asset->save();
                        $success++;
                    } catch (Throwable $e) {
                        // Gagal hapus dari storage → jangan ubah DB, skip, lanjut record berikutnya.
                        Log::error('assets:hard-delete-expired failed for asset', [
                            'asset_id' => $asset->id,
                            'path' => $asset->path,
                            'error' => $e->getMessage(),
                        ]);
                        $failed++;
                    }
                }
            });

        $this->info("Hard-deleted {$success} asset(s), {$failed} failed.");
        Log::info('assets:hard-delete-expired completed', ['success' => $success, 'failed' => $failed]);

        return self::SUCCESS;
    }
}
