<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Ekstrak metadata teknis dari file sebelum diupload.
 *
 * Catatan: ekstraksi image (getimagesize) & PDF (heuristik page count) bersifat
 * dependency-free dan selalu jalan. Untuk durasi video/audio dibutuhkan ffprobe
 * atau getID3 (tidak dibundel di starter ini) — method-nya disediakan sebagai
 * extension point yang mengembalikan array kosong bila tooling tidak tersedia.
 */
class AssetMetadataExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extract(UploadedFile $file): array
    {
        $mime = $file->getMimeType() ?? '';

        return match (true) {
            str_starts_with($mime, 'image/') => $this->imageMetadata($file),
            str_starts_with($mime, 'video/') => $this->videoMetadata($file),
            str_starts_with($mime, 'audio/') => $this->audioMetadata($file),
            $mime === 'application/pdf' => $this->pdfMetadata($file),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function imageMetadata(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return [];
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return [];
        }

        return [
            'width' => $info[0],
            'height' => $info[1],
        ];
    }

    /**
     * Best-effort: menghitung jumlah halaman PDF tanpa dependency eksternal
     * dengan mencocokkan penanda objek "/Type /Page" pada byte mentah file.
     *
     * @return array<string, mixed>
     */
    private function pdfMetadata(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return [];
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }

        // Negative lookahead agar "/Pages" (node induk) tidak ikut terhitung.
        $pages = preg_match_all('/\/Type\s*\/Page(?![s])/', $content);

        return $pages > 0 ? ['pages' => $pages] : [];
    }

    /**
     * Extension point: durasi/dimensi video butuh ffprobe atau getID3.
     *
     * @return array<string, mixed>
     */
    private function videoMetadata(UploadedFile $file): array
    {
        return [];
    }

    /**
     * Extension point: durasi audio butuh ffprobe atau getID3.
     *
     * @return array<string, mixed>
     */
    private function audioMetadata(UploadedFile $file): array
    {
        return [];
    }
}
