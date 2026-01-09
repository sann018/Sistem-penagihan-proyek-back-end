<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MitraController extends Controller
{
    private const SPECIAL_ALL_ACCESS_MITRA = 'Telkom Akses';

    /**
     * [🔐 MITRA_ACCOUNT] Dropdown mitra dinamis
     * Source of truth:
     * SELECT DISTINCT nama_mitra FROM data_proyek WHERE nama_mitra IS NOT NULL
     */
    public function options(): JsonResponse
    {
        $mitra = DB::table('data_proyek')
            ->select('nama_mitra')
            ->whereNotNull('nama_mitra')
            ->whereRaw("TRIM(nama_mitra) != ''")
            ->distinct()
            ->orderBy('nama_mitra')
            ->pluck('nama_mitra')
            ->values();

        // Catatan: Jika tabel proyek kosong / semua nama_mitra kosong, dropdown harus kosong.
        // Opsi spesial hanya ditambahkan jika memang ada data mitra dari proyek.
        if ($mitra->isNotEmpty()
            && !$mitra->contains(fn ($m) => is_string($m) && strcasecmp(trim($m), self::SPECIAL_ALL_ACCESS_MITRA) === 0)
        ) {
            $mitra = collect([self::SPECIAL_ALL_ACCESS_MITRA])->merge($mitra)->values();
        }

        return response()->json([
            'success' => true,
            // Kept simple for frontend dropdown
            'data' => $mitra,
            // Alias key for flexibility
            'mitra_options' => $mitra,
        ]);
    }
}
