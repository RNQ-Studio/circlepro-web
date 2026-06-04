<?php

namespace App\Filament\Resources\TargetFaces\Pages;

use App\Filament\Resources\TargetFaces\TargetFaceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTargetFace extends EditRecord
{
    protected static string $resource = TargetFaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
