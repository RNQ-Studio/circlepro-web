<?php

namespace App\Filament\Resources\TargetFaces;

use App\Filament\Resources\TargetFaces\Pages\CreateTargetFace;
use App\Filament\Resources\TargetFaces\Pages\EditTargetFace;
use App\Filament\Resources\TargetFaces\Pages\ListTargetFaces;
use App\Filament\Resources\TargetFaces\Schemas\TargetFaceForm;
use App\Filament\Resources\TargetFaces\Tables\TargetFacesTable;
use App\Models\TargetFace;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TargetFaceResource extends Resource
{
    protected static ?string $model = TargetFace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewfinderCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TargetFaceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TargetFacesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTargetFaces::route('/'),
            'create' => CreateTargetFace::route('/create'),
            'edit' => EditTargetFace::route('/{record}/edit'),
        ];
    }
}
