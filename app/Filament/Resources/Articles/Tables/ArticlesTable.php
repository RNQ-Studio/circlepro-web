<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Support\Enums\ArticleStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('featured_image')
                    ->label('Thumbnail')
                    ->square()
                    ->size(50),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('author.name')
                    ->label('Penulis')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (ArticleStatus $state): string => match ($state) {
                        ArticleStatus::Draft => 'gray',
                        ArticleStatus::Published => 'success',
                    })
                    ->sortable(),
                TextColumn::make('reading_time')
                    ->label('Waktu Baca')
                    ->formatStateUsing(fn (int $state): string => "{$state} min read")
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Tanggal Terbit')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ArticleStatus::class),
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                SelectFilter::make('author_id')
                    ->label('Penulis')
                    ->relationship('author', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
