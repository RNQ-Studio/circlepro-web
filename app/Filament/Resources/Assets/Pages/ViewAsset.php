<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Actions\Action;
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
            Action::make('download')
                ->label('Unduh')
                ->icon('heroicon-o-arrow-down-tray')
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
        ];
    }
}
