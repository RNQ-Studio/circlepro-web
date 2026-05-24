<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Models\Asset;
use App\Support\Enums\AssetStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Throwable;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_filename')
                    ->label('Nama File')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn (Asset $record): string => $record->original_filename),
                TextColumn::make('mime_type')
                    ->label('Jenis')
                    ->badge()
                    // Tampilkan kategori file yang ramah, bukan mime mentah.
                    ->formatStateUsing(fn (string $state): string => self::humanType($state))
                    ->sortable(),
                TextColumn::make('size')
                    ->label('Ukuran')
                    // size disimpan dalam bytes → format human-readable (KB/MB/GB).
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state, precision: 2))
                    ->sortable(),
                TextColumn::make('storage_type')
                    ->label('Storage')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Asset $record): string => match ($record->status) {
                        AssetStatus::Active => 'success',
                        AssetStatus::SoftDeleted => 'warning',
                        AssetStatus::HardDeleted => 'danger',
                    }),
                TextColumn::make('creator.name')
                    ->label('Uploader')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Tanggal Upload')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis File')
                    ->options([
                        'image' => 'Gambar',
                        'video' => 'Video',
                        'audio' => 'Audio',
                        'application' => 'Dokumen',
                        'text' => 'Teks',
                    ])
                    // Filter berdasarkan prefix mime type (image/*, video/*, dst).
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->where('mime_type', 'like', $data['value'].'/%')
                        : $query),
                // Memungkinkan admin melihat file yang sudah di-trash.
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::downloadAction(),
                // DeleteAction = soft delete (model pakai SoftDeletes) + modal konfirmasi bawaan.
                DeleteAction::make()
                    ->label('Hapus')
                    ->modalHeading('Hapus Asset')
                    ->modalDescription('File akan dipindahkan ke trash. Lanjutkan?'),
                RestoreAction::make()->label('Pulihkan'),
                ForceDeleteAction::make()->label('Hapus Permanen'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Aksi download: GCS → redirect ke temporary signed URL (5 menit);
     * local → stream langsung. Tangani file yang tidak ada di storage.
     */
    protected static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Unduh')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            // Sembunyikan untuk record yang sudah di-trash (file mungkin terjadwal hapus).
            ->hidden(fn (Asset $record): bool => $record->trashed())
            ->action(function (Asset $record) {
                $disk = Storage::disk($record->storage_type->disk());

                try {
                    if (! $disk->exists($record->path)) {
                        Notification::make()
                            ->title('File tidak ditemukan di storage')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if ($record->isGCS()) {
                        // Signed URL kedaluwarsa dalam 5 menit.
                        return redirect($disk->temporaryUrl($record->path, now()->addMinutes(5)));
                    }

                    return $disk->download($record->path, $record->original_filename);
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Gagal mengunduh file')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }
            });
    }

    /** Petakan mime type ke label kategori berbahasa Indonesia. */
    protected static function humanType(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'Gambar',
            str_starts_with($mime, 'video/') => 'Video',
            str_starts_with($mime, 'audio/') => 'Audio',
            str_starts_with($mime, 'text/') => 'Teks',
            str_starts_with($mime, 'application/') => 'Dokumen',
            default => $mime,
        };
    }
}
