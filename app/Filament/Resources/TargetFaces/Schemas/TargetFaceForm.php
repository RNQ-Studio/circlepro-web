<?php

namespace App\Filament\Resources\TargetFaces\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                    ->maxLength(2048)
                    ->nullable()
                    ->live(onBlur: true),
                FileUpload::make('image_upload')
                    ->label('Unggah Gambar Baru')
                    ->image()
                    ->maxSize(5120)
                    ->dehydrated(false)
                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, callable $set, $record) {
                        $asset = app(\App\Services\AssetUploadService::class)->upload(
                            file: $file,
                            type: 'target_face',
                            userId: auth()->id(),
                        );
                        $set('image_path', $asset->url);
                        
                        if ($record) {
                            $record->update([
                                'image_path' => $asset->url,
                            ]);
                        }
                        
                        return $asset->id;
                    }),
                Placeholder::make('image_preview')
                    ->label('Pratinjau Gambar')
                    ->content(fn (callable $get) => $get('image_path')
                        ? new \Illuminate\Support\HtmlString('<img src="' . (str_starts_with($get('image_path'), 'http') ? $get('image_path') : asset($get('image_path'))) . '" style="max-height: 120px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); background: #f3f4f6; padding: 4px;" />')
                        : 'Tidak ada pratinjau'
                    ),
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
