<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Services\AssetDeletionService;
use App\Support\Enums\AssetStatus;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Ubah')
                ->icon('heroicon-o-pencil')
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
                ]),

            Action::make('download')
                ->label('Unduh')
                ->icon('heroicon-o-arrow-down-tray')
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
                }),

            Action::make('hardDelete')
                ->label('Hapus')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Hapus Permanen')
                ->modalDescription('File akan DIHAPUS PERMANEN dari Google Cloud Storage dan tidak bisa dipulihkan. Lanjutkan?')
                ->modalSubmitActionLabel('Ya, hapus permanen')
                ->hidden(fn (Asset $record): bool => $record->status === AssetStatus::HardDeleted)
                ->authorize(fn (Asset $record): bool => auth()->user()?->can('forceDelete', $record) ?? false)
                ->action(function (Asset $record) {
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

                        // Kembali ke daftar; record kini tombstone tanpa file.
                        $this->redirect(AssetResource::getUrl('index'));
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Gagal menghapus asset')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
