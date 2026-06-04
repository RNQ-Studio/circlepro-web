<?php

namespace App\Filament\Resources\TargetFaces\Pages;

use App\Filament\Resources\TargetFaces\TargetFaceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTargetFaces extends ListRecords
{
    protected static string $resource = TargetFaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
