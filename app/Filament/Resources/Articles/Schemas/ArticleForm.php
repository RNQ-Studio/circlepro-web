<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Tag;
use App\Support\Enums\ArticleStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // Left column: Main Content (2/3 width)
                        Group::make([
                            Section::make()
                                ->schema([
                                    TextInput::make('title')
                                        ->label('Judul Artikel')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn (string $operation, ?string $state, callable $set): mixed => $operation === 'create'
                                            ? $set('slug', Str::slug((string) $state))
                                            : null)
                                        ->extraInputAttributes(['style' => 'font-size: 1.25rem; font-weight: bold; height: auto;']),

                                    TextInput::make('slug')
                                        ->label('Slug / URL')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),

                                    Textarea::make('excerpt')
                                        ->label('Ringkasan Singkat / Subtitle')
                                        ->rows(2)
                                        ->helperText('Cuplikan paragraf pertama atau ringkasan untuk menarik pembaca.')
                                        ->maxLength(500),

                                    RichEditor::make('content')
                                        ->label('Konten Artikel')
                                        ->required()
                                        ->fileAttachmentsDirectory('articles/attachments')
                                        ->columnSpanFull(),
                                ]),
                        ])->columnSpan(2),

                        // Right column: Metadata & Options (1/3 width)
                        Group::make([
                            Section::make('Kategori & Tags')
                                ->schema([
                                    Select::make('category_id')
                                        ->label('Kategori')
                                        ->relationship('category', 'name', fn ($query) => $query->where('is_active', true))
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    Select::make('tags')
                                        ->label('Tags')
                                        ->multiple()
                                        ->relationship('tags', 'name')
                                        ->preload()
                                        ->createOptionForm([
                                            TextInput::make('name')
                                                ->required()
                                                ->maxLength(255)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn (string $operation, ?string $state, callable $set): mixed => $set('slug', Str::slug((string) $state))),
                                            TextInput::make('slug')
                                                ->required()
                                                ->unique(Tag::class, 'slug')
                                                ->maxLength(255),
                                        ])
                                        ->searchable(),
                                ]),

                            Section::make('Publikasi')
                                ->schema([
                                    Select::make('author_id')
                                        ->label('Penulis')
                                        ->relationship('author', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->default(fn () => auth()->id())
                                        ->required(),

                                    Select::make('status')
                                        ->label('Status')
                                        ->options(ArticleStatus::class)
                                        ->default(ArticleStatus::Draft)
                                        ->required()
                                        ->live(),

                                    DateTimePicker::make('published_at')
                                        ->label('Tanggal Publikasi')
                                        ->helperText('Kosongkan untuk otomatis diisi tanggal saat ini jika status Published.'),

                                    TextInput::make('reading_time')
                                        ->label('Waktu Baca (Menit)')
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->placeholder('Otomatis dihitung...'),
                                ]),

                            Section::make('Gambar Utama')
                                ->schema([
                                    FileUpload::make('featured_image')
                                        ->label('Featured Image')
                                        ->image()
                                        ->directory('articles/featured')
                                        ->maxSize(5120) // 5 MB
                                        ->helperText('Format: JPG, PNG, WEBP. Maks 5 MB.'),
                                ]),
                        ])->columnSpan(1),
                    ]),
            ]);
    }
}
