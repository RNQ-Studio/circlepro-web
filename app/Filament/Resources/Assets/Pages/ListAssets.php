<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Exports\AssetsExport;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Widgets\AssetStatsOverview;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Unggah Asset'),
            Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn (): BinaryFileResponse => $this->exportToExcel()),
        ];
    }

    /** Ekspor sesuai filter/urutan tabel yang sedang aktif ("export what you see"). */
    protected function exportToExcel(): BinaryFileResponse
    {
        $filename = 'assets_export_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new AssetsExport($this->getFilteredSortedTableQuery()),
            $filename,
        );
    }

    /** Stats ditampilkan sebagai header widget di atas tabel. */
    protected function getHeaderWidgets(): array
    {
        return [
            AssetStatsOverview::class,
        ];
    }
}
