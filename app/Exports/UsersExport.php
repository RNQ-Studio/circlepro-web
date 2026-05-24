<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * Mengambil seluruh data users untuk diekspor.
     */
    public function collection(): Collection
    {
        return User::all();
    }

    /**
     * Menentukan heading/header kolom pada file Excel.
     */
    public function headings(): array
    {
        return [
            'id',
            'name',
            'email',
            'created_at',
        ];
    }

    /**
     * Memetakan kolom data user yang akan diekspor.
     *
     * @param  mixed  $user
     */
    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->created_at ? $user->created_at->toDateTimeString() : null,
        ];
    }
}
