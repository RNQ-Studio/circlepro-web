<?php

namespace App\Filament\Resources\TargetFaces\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TargetFaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $operation, ?string $state, callable $set): mixed => $operation === 'create'
                        ? $set('code', Str::slug((string) $state, '_'))
                        : null)
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('image_path')
                    ->label('Image Path / URL')
                    ->maxLength(255)
                    ->nullable(),
                TextInput::make('used_count')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Repeater::make('scoring_rules')
                    ->schema([
                        TextInput::make('value')
                            ->numeric()
                            ->required()
                            ->label('Value / Points'),
                        TextInput::make('label')
                            ->required()
                            ->label('Label (e.g. X, 10, 9, M)'),
                        ColorPicker::make('color')
                            ->required()
                            ->label('Color Hex'),
                        Toggle::make('is_x')
                            ->label('Is X / Inner 10'),
                        Toggle::make('is_miss')
                            ->label('Is Miss (M)'),
                    ])
                    ->grid(2)
                    ->columnSpanFull()
                    ->required(),
            ]);
    }
}
