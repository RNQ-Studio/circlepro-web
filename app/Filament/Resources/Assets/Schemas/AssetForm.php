<?php

namespace App\Filament\Resources\Assets\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AssetForm
{
    /** Daftar MIME type yang diizinkan — mirror aturan `mimes:` di UploadAssetRequest (API). */
    private const ACCEPTED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
        'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm',
        'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('file')
                ->label('File')
                ->required()
                // Jangan simpan ke disk dari sini — biarkan AssetUploadService yang
                // mengunggah ke GCS + membuat record secara atomik. State akan berupa
                // TemporaryUploadedFile yang langsung diteruskan ke service.
                ->storeFiles(false)
                ->maxSize(51200) // 50 MB (KB), sama dengan batas API
                ->acceptedFileTypes(self::ACCEPTED_MIME_TYPES)
                ->helperText('Maksimal 50 MB. Gambar, video, audio, atau dokumen.')
                ->columnSpanFull(),

            TextInput::make('type')
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
                ->helperText('Jika aktif, file diabaikan oleh scheduler pembersih dan tidak bisa di-soft-delete.')
                ->default(false),
        ]);
    }
}
