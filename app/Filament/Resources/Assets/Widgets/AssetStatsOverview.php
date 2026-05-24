<?php

namespace App\Filament\Resources\Assets\Widgets;

use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * Diletakkan di bawah direktori resource (bukan app/Filament/Widgets) agar
 * TIDAK ikut ter-discover ke Dashboard global — widget ini hanya dipakai
 * sebagai header widget di halaman daftar Asset.
 */
class AssetStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        // Hanya hitung asset aktif (belum di-trash) — query default model
        // sudah menerapkan SoftDeletingScope.
        $total = Asset::query()->count();
        $totalSize = (int) Asset::query()->sum('size');

        $images = Asset::query()->where('mime_type', 'like', 'image/%')->count();
        $videos = Asset::query()->where('mime_type', 'like', 'video/%')->count();
        $documents = Asset::query()->where('mime_type', 'like', 'application/%')->count();

        return [
            Stat::make('Total File', Number::format($total))
                ->description('File aktif tersimpan')
                ->descriptionIcon('heroicon-m-document')
                ->color('info'),
            Stat::make('Total Ukuran', Number::fileSize($totalSize, precision: 2))
                ->description('Akumulasi seluruh file')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('success'),
            Stat::make('Breakdown Jenis', Number::format($images).' gambar')
                ->description(Number::format($videos).' video • '.Number::format($documents).' dokumen')
                ->descriptionIcon('heroicon-m-photo')
                ->color('warning'),
        ];
    }
}
