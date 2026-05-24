<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SoftDeleteExpiredAssets extends Command
{
    protected $signature = 'assets:soft-delete-expired {--days=30 : Hari sebelum hard delete dijadwalkan} {--chunk=500}';

    protected $description = 'Tandai asset yang melewati retain_until sebagai soft_deleted dan jadwalkan hard delete';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunk = (int) $this->option('chunk');
        $processed = 0;

        // scopeExpired sudah memfilter status=active, retain_until tidak null & sudah lewat.
        // is_protected dikecualikan agar file legal/compliance tidak pernah tersentuh.
        // chunkById dipakai (bukan chunk) karena kita memutasi status di tiap iterasi.
        Asset::query()
            ->expired()
            ->where('is_protected', false)
            ->chunkById($chunk, function (Collection $assets) use ($days, &$processed): void {
                /** @var Collection<int, Asset> $assets */
                foreach ($assets as $asset) {
                    $asset->markAsSoftDeleted($days);
                    $processed++;
                }
            });

        $this->info("Soft-deleted {$processed} expired asset(s).");
        Log::info('assets:soft-delete-expired completed', ['processed' => $processed, 'days' => $days]);

        return self::SUCCESS;
    }
}
