<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Services\AssetUploadService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    /**
     * Upload via AssetUploadService agar logika storage GCS, checksum, ekstraksi
     * metadata, dan kompensasi orphan persis sama dengan jalur API.
     */
    protected function handleRecordCreation(array $data): Model
    {
        // FileUpload dengan storeFiles(false) menyimpan state sebagai array
        // [uuid => TemporaryUploadedFile]; ambil file pertama.
        $file = Arr::first(Arr::wrap($data['file']));

        if (! $file instanceof TemporaryUploadedFile) {
            throw new \RuntimeException('File unggahan tidak valid.');
        }

        return app(AssetUploadService::class)->upload(
            file: $file,
            type: $data['type'],
            userId: auth()->id(),
            retainUntil: filled($data['retain_until'] ?? null)
                ? Carbon::parse($data['retain_until'])
                : null,
            isProtected: (bool) ($data['is_protected'] ?? false),
        );
    }
}
