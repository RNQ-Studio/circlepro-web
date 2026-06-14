<?php

namespace App\Filament\Resources\TargetFaces\Schemas;

use App\Models\Asset;
use App\Models\TargetFace;
use App\Services\AssetDeletionService;
use App\Services\AssetUploadService;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
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
                    ->live()
                    ->formatStateUsing(function (?TargetFace $record) {
                        if (! $record || ! $record->image_path) {
                            return null;
                        }
                        $asset = Asset::where('url', $record->image_path)->first();

                        return $asset?->id;
                    })
                    ->afterStateUpdated(function ($state, callable $set, ?TargetFace $record) {
                        if ($state instanceof TemporaryUploadedFile) {
                            $asset = app(AssetUploadService::class)->upload(
                                file: $state,
                                type: 'target_face',
                                userId: auth()->id(),
                            );
                            $set('image_path', $asset->url);
                            $set('image_upload', $asset->id);

                            if ($record) {
                                $record->update([
                                    'image_path' => $asset->url,
                                ]);
                            }
                        }
                    })
                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, callable $set, ?TargetFace $record) {
                        $asset = app(AssetUploadService::class)->upload(
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
                    })
                    ->getUploadedFileUsing(function ($file) {
                        if (! $file) {
                            return null;
                        }
                        if (Str::isUuid($file)) {
                            $asset = Asset::find($file);
                            if ($asset) {
                                return [
                                    'name' => $asset->original_filename,
                                    'size' => $asset->size,
                                    'type' => $asset->mime_type,
                                    'url' => $asset->url,
                                ];
                            }
                        }

                        return null;
                    })
                    ->deleteUploadedFileUsing(function ($state, callable $set, ?TargetFace $record) {
                        if (! $state) {
                            return;
                        }
                        $set('image_path', null);

                        if ($record) {
                            $record->update([
                                'image_path' => null,
                            ]);
                        }

                        if (Str::isUuid($state)) {
                            $asset = Asset::find($state);
                            if ($asset) {
                                app(AssetDeletionService::class)->hardDelete($asset);
                            }
                        }
                    }),
                Placeholder::make('image_preview')
                    ->label('Pratinjau Gambar')
                    ->content(fn (callable $get) => $get('image_path')
                        ? new HtmlString('<img src="'.(str_starts_with($get('image_path'), 'http') ? $get('image_path') : asset($get('image_path'))).'" style="max-height: 120px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); background: #f3f4f6; padding: 4px;" />')
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
