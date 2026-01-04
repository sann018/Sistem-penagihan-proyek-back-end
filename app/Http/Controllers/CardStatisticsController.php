<?php

namespace App\Http\Controllers;

use App\Models\Penagihan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CardStatisticsController
 * 
 * Controller terpisah untuk handle card statistics dengan caching
 * untuk performa yang lebih baik
 */
class CardStatisticsController extends Controller
{
    /**
     * Get card statistics dengan caching untuk performa optimal
     * Cache akan di-clear otomatis saat ada update data proyek
     * 
     * ✅ OPTIMASI: Menggunakan single query dengan CASE untuk performa maksimal
     */
    public function getCardStatistics(): JsonResponse
    {
        // Cache selama 5 menit (300 detik)
        $statistics = Cache::remember('card_statistics', 300, function () {
            // ✅ Single query dengan CASE untuk semua statistik (5-10x lebih cepat)
            $stats = DB::table('data_proyek')->selectRaw("
                COUNT(*) as total_proyek,
                SUM(CASE 
                    WHEN LOWER(status_ct) = 'sudah ct' 
                    AND LOWER(status_ut) = 'sudah ut' 
                    AND LOWER(rekap_boq) = 'sudah rekap' 
                    AND LOWER(rekon_material) = 'sudah rekon' 
                    AND LOWER(pelurusan_material) = 'sudah lurus' 
                    AND LOWER(status_procurement) = 'otw reg' 
                    THEN 1 ELSE 0 
                END) as sudah_penuh,
                SUM(CASE 
                    WHEN (LOWER(status_ct) != 'sudah ct' 
                        OR LOWER(status_ut) != 'sudah ut' 
                        OR LOWER(rekap_boq) != 'sudah rekap' 
                        OR LOWER(rekon_material) != 'sudah rekon' 
                        OR LOWER(pelurusan_material) != 'sudah lurus' 
                        OR LOWER(status_procurement) != 'otw reg')
                    AND LOWER(status_procurement) != 'revisi mitra'
                    THEN 1 ELSE 0 
                END) as sedang_berjalan,
                SUM(CASE 
                    WHEN LOWER(status_procurement) = 'revisi mitra' 
                    THEN 1 ELSE 0 
                END) as tertunda,
                SUM(CASE 
                    WHEN LOWER(rekap_boq) = 'belum rekap' 
                    THEN 1 ELSE 0 
                END) as belum_rekon
            ")->first();
            
            return [
                'total_proyek' => $stats->total_proyek,
                'sudah_penuh' => $stats->sudah_penuh,
                'sedang_berjalan' => $stats->sedang_berjalan,
                'tertunda' => $stats->tertunda,
                'belum_rekon' => $stats->belum_rekon,
            ];
        });

        return response()->json($statistics);
    }

    /**
     * Clear card statistics cache
     * Dipanggil saat ada perubahan data proyek
     */
    public static function clearCache(): void
    {
        Cache::forget('card_statistics');
    }
}
