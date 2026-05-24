<?php

namespace App\Exports;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetsExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Asset>|null  $query  Query tabel yang sudah difilter/diurutkan dari
     *                                      halaman list. Bila null, ekspor seluruh asset aktif.
     */
    public function __construct(
        private readonly ?Builder $query = null,
    ) {}

    /**
     * FromQuery dipakai (bukan FromCollection) agar Laravel Excel mem-chunk
     * query — aman untuk jumlah asset yang besar.
     *
     * @return Builder<Asset>
     */
    public function query(): Builder
    {
        return $this->query ?? Asset::query();
    }

    /**
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Nama File',
            'Jenis (MIME)',
            'Ukuran',
            'Storage',
            'Kategori',
            'Status',
            'Uploader',
            'Dilindungi',
            'Link File',
            'Retensi Hingga',
            'Tanggal Upload',
        ];
    }

    /**
     * @param  Asset  $asset
     * @return array<int, string|null>
     */
    public function map($asset): array
    {
        return [
            $asset->id,
            $asset->original_filename,
            $asset->mime_type,
            Number::fileSize($asset->size, precision: 2),
            $asset->storage_type->value,
            $asset->category,
            $asset->status->value,
            $asset->creator?->name,
            $asset->is_protected ? 'Ya' : 'Tidak',
            // Link publik stabil (kolom url ter-cache / dibangun dari disk),
            // bukan signed URL yang kedaluwarsa.
            $asset->getPublicUrl(),
            $asset->retain_until?->toDateTimeString(),
            $asset->created_at?->toDateTimeString(),
        ];
    }
}
