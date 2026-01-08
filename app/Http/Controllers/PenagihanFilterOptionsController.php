<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PenagihanFilterOptionsController extends Controller
{
    private const SPECIAL_ALL_ACCESS_MITRA = 'Telkom Akses';

    /**
     * Dropdown filter options untuk penagihan.
     * Source of truth: tabel data_proyek.
     *
     * Khusus super_admin & admin (lihat routes/api.php).
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
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values();

        // Pastikan opsi "Telkom Akses" tersedia (untuk akses semua mitra pada akun viewer tertentu)
        if (!$mitra->contains(fn ($m) => is_string($m) && strcasecmp(trim($m), self::SPECIAL_ALL_ACCESS_MITRA) === 0)) {
            $mitra = collect([self::SPECIAL_ALL_ACCESS_MITRA])->merge($mitra)->values();
        }

        $jenisPo = DB::table('data_proyek')
            ->select('jenis_po')
            ->whereNotNull('jenis_po')
            ->whereRaw("TRIM(jenis_po) != ''")
            ->distinct()
            ->orderBy('jenis_po')
            ->pluck('jenis_po')
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values();

        $phase = DB::table('data_proyek')
            ->select('phase')
            ->whereNotNull('phase')
            ->whereRaw("TRIM(phase) != ''")
            ->distinct()
            ->orderBy('phase')
            ->pluck('phase')
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'mitra' => $mitra,
                'jenis_po' => $jenisPo,
                'phase' => $phase,
            ],
        ]);
    }
}
