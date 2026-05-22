<?php

namespace App\Filament\Resources\AppConfigs\Pages;

use App\Filament\Resources\AppConfigs\AppConfigResource;
use Filament\Resources\Pages\ListRecords;

class ListAppConfigs extends ListRecords
{
    protected static string $resource = AppConfigResource::class;
}
