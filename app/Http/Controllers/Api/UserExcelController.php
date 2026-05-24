<?php

namespace App\Http\Controllers\Api;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Imports\UsersImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Exporter;
use Maatwebsite\Excel\Importer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class UserExcelController extends Controller
{
    /**
     * Mengunduh semua data users dalam format .xlsx.
     */
    public function export(Exporter $exporter): BinaryFileResponse|JsonResponse
    {
        try {
            $date = date('Ymd');
            $fileName = "users_export_{$date}.xlsx";

            // Menggunakan Dependency Injection Exporter untuk memicu download
            return $exporter->download(new UsersExport, $fileName);
        } catch (Throwable $e) {
            // Sesuai dengan spesifikasi format response gagal
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Mengimpor data users dari file Excel (.xlsx).
     */
    public function import(Request $request, Importer $importer): JsonResponse
    {
        // Validasi input file: wajib ada dan valid
        if (! $request->hasFile('file') || ! $request->file('file')->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak valid. Harap upload file .xlsx',
                'data' => null,
            ], 400);
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        // Validasi format file harus .xlsx
        if (strtolower($extension) !== 'xlsx') {
            return response()->json([
                'success' => false,
                'message' => 'File tidak valid. Harap upload file .xlsx',
                'data' => null,
            ], 400);
        }

        try {
            $import = new UsersImport;
            $importer->import($import, $file);

            // Return response ringkasan sesuai dengan format spesifikasi
            return response()->json([
                'success' => true,
                'message' => 'Import selesai',
                'data' => $import->getSummary(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses file import: '.$e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}
