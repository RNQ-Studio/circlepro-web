<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Widgets\AssetStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Unggah Asset'),
        ];
    }

    /** Stats ditampilkan sebagai header widget di atas tabel. */
    protected function getHeaderWidgets(): array
    {
        return [
            AssetStatsOverview::class,
        ];
    }
}
