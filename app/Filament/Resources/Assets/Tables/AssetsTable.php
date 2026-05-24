<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Models\Asset;
use App\Services\AssetDeletionService;
use App\Support\Enums\AssetStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
                    // Pencarian jalan di nilai DB (mime_type), bukan label tampilan.
                    // Terjemahkan label Indonesia ("Gambar" → image/%) agar cocok,
                    // tapi tetap izinkan pencarian mime mentah ("pdf", "jpeg").
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $prefix = self::typeToMimePrefix($search);

                        return $query->where(function (Builder $q) use ($search, $prefix): void {
                            if ($prefix !== null) {
                                $q->where('mime_type', 'like', $prefix.'/%');
                            }
                            $q->orWhere('mime_type', 'like', '%'.$search.'%');
                        });
                    })
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
            ])
            ->recordActions([
                ViewAction::make(),
                self::editAction(),
                self::downloadAction(),
                self::hardDeleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::hardDeleteBulkAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Aksi Hapus = hard delete permanen: file dihapus dari storage (GCS) dan
     * record ditandai hard_deleted (tombstone). Tidak bisa dipulihkan.
     */
    protected static function hardDeleteAction(): Action
    {
        return Action::make('hardDelete')
            ->label('Hapus')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus Permanen')
            ->modalDescription('File akan DIHAPUS PERMANEN dari Google Cloud Storage dan tidak bisa dipulihkan. Lanjutkan?')
            ->modalSubmitActionLabel('Ya, hapus permanen')
            // Sudah hard_deleted → tidak ada yang bisa dihapus lagi.
            ->hidden(fn (Asset $record): bool => $record->status === AssetStatus::HardDeleted)
            ->authorize(fn (Asset $record): bool => auth()->user()?->can('forceDelete', $record) ?? false)
            ->action(function (Asset $record): void {
                if ($record->is_protected) {
                    Notification::make()
                        ->title('File dilindungi')
                        ->body('Asset ini ditandai dilindungi (legal hold) dan tidak bisa dihapus.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    app(AssetDeletionService::class)->hardDelete($record);
                    Notification::make()->title('Asset dihapus permanen')->success()->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Gagal menghapus asset')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /** Hapus permanen massal; file dilindungi dilewati, sisanya dihapus dari storage. */
    protected static function hardDeleteBulkAction(): BulkAction
    {
        return BulkAction::make('hardDeleteBulk')
            ->label('Hapus Permanen')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus Permanen')
            ->modalDescription('File terpilih akan dihapus permanen dari Google Cloud Storage. File yang dilindungi akan dilewati. Lanjutkan?')
            ->modalSubmitActionLabel('Ya, hapus permanen')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $service = app(AssetDeletionService::class);
                $deleted = 0;
                $skipped = 0;
                $failed = 0;

                /** @var Collection<int, Asset> $records */
                foreach ($records as $record) {
                    if ($record->is_protected) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $service->hardDelete($record);
                        $deleted++;
                    } catch (Throwable) {
                        $failed++;
                    }
                }

                Notification::make()
                    ->title("Hapus permanen: {$deleted} berhasil, {$skipped} dilindungi (dilewati), {$failed} gagal")
                    ->success()
                    ->send();
            });
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
            // Sembunyikan untuk record yang sudah hard_deleted (file fisik sudah hilang).
            ->hidden(fn (Asset $record): bool => $record->status === AssetStatus::HardDeleted)
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

    /**
     * Terjemahkan kata kunci pencarian (label Indonesia/Inggris) ke prefix mime.
     * Cocok substring, jadi "gam" tetap mengarah ke "image". Null bila tak dikenal.
     */
    protected static function typeToMimePrefix(string $search): ?string
    {
        $search = mb_strtolower(trim($search));

        if ($search === '') {
            return null;
        }

        $map = [
            'gambar' => 'image',
            'image' => 'image',
            'foto' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            'suara' => 'audio',
            'dokumen' => 'application',
            'document' => 'application',
            'teks' => 'text',
            'text' => 'text',
        ];

        foreach ($map as $keyword => $prefix) {
            if (str_contains($keyword, $search)) {
                return $prefix;
            }
        }

        return null;
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

    protected static function editAction(): EditAction
    {
        return EditAction::make()
            ->label('Ubah')
            ->icon('heroicon-o-pencil')
            ->color('warning')
            ->hidden(fn (Asset $record): bool => $record->status === AssetStatus::HardDeleted)
            ->form([
                TextInput::make('category')
                    ->label('Jenis / Kategori')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Kategori logis file, mis. bukti_transfer, foto_profil, lampiran.'),

                DateTimePicker::make('retain_until')
                    ->label('Retensi Hingga')
                    ->seconds(false)
                    ->helperText('Kosongkan untuk menjadikan file permanen (tidak terhapus otomatis).'),

                Toggle::make('is_protected')
                    ->label('Lindungi (legal/compliance hold)')
                    ->helperText('Jika aktif, file diabaikan oleh scheduler pembersih dan tidak bisa di-soft-delete.'),
            ]);
    }
}
