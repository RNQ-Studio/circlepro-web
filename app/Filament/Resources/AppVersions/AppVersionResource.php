<?php

namespace App\Filament\Resources\AppVersions;

use App\Filament\Resources\AppVersions\Pages\ListAppVersions;
use App\Models\AppVersion;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AppVersionResource extends Resource
{
    protected static ?string $model = AppVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'App Management';

    protected static ?string $navigationLabel = 'App Versions';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform')
                ->options(['android' => 'Android', 'ios' => 'iOS'])
                ->required()
                ->disabled(fn ($record) => $record !== null),
            TextInput::make('min_version')
                ->label('Minimum Version')
                ->required()
                ->maxLength(20)
                ->placeholder('1.0.0'),
            TextInput::make('latest_version')
                ->label('Latest Version')
                ->required()
                ->maxLength(20)
                ->placeholder('1.0.0'),
            Toggle::make('force_update')
                ->label('Force Update')
                ->helperText('When enabled, users below the minimum version are forced to update.'),
            TextInput::make('store_url')
                ->label('Store URL')
                ->url()
                ->maxLength(500)
                ->nullable(),
            Textarea::make('release_notes')
                ->label('Release Notes')
                ->nullable()
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'android' ? 'success' : 'info'),
                TextColumn::make('min_version')->label('Min Version'),
                TextColumn::make('latest_version')->label('Latest Version'),
                IconColumn::make('force_update')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppVersions::route('/'),
        ];
    }
}
