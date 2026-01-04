<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class DataCleanupController extends Controller
{
    /**
     * Ambil daftar tahun yang tersedia berdasarkan data di database.
     * Sumber: aktivitas_sistem(waktu_kejadian), log_aktivitas(waktu_kejadian), notifikasi(waktu_dibuat)
     * SUPER ADMIN ONLY (enforced by route middleware).
     */
    public function getAvailableYears(Request $request): JsonResponse
    {
        try {
            $yearsAktivitas = DB::table('aktivitas_sistem')
                ->selectRaw('YEAR(waktu_kejadian) as year')
                ->whereNotNull('waktu_kejadian')
                ->distinct()
                ->pluck('year')
                ->filter()
                ->map(fn ($y) => (int) $y)
                ->all();

            $yearsLogAktivitas = DB::table('log_aktivitas')
                ->selectRaw('YEAR(waktu_kejadian) as year')
                ->whereNotNull('waktu_kejadian')
                ->distinct()
                ->pluck('year')
                ->filter()
                ->map(fn ($y) => (int) $y)
                ->all();

            $yearsNotifikasi = DB::table('notifikasi')
                ->selectRaw('YEAR(waktu_dibuat) as year')
                ->whereNotNull('waktu_dibuat')
                ->distinct()
                ->pluck('year')
                ->filter()
                ->map(fn ($y) => (int) $y)
                ->all();

            $years = collect(array_merge($yearsAktivitas, $yearsLogAktivitas, $yearsNotifikasi))
                ->unique()
                ->sortDesc()
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'data' => [
                    'years' => $years,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar tahun: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus data aktivitas_sistem yang lebih lama dari tanggal yang ditentukan.
     * SUPER ADMIN ONLY (enforced by route middleware).
     */
    public function cleanupAktivitasSistem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:1970|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bulan = (int) $request->get('bulan');
        $tahun = (int) $request->get('tahun');

        // Buat range tanggal untuk bulan spesifik (awal sampai akhir bulan)
        $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth();

        if ($akhirBulan->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus data masa depan.',
            ], 400);
        }

        try {
            // Hapus HANYA data pada bulan dan tahun yang dipilih
            $deleted = DB::table('aktivitas_sistem')
                ->whereBetween('waktu_kejadian', [$awalBulan, $akhirBulan])
                ->delete();

            Log::info('[CLEANUP] Aktivitas Sistem dibersihkan', [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'deleted_count' => $deleted,
                'user_id' => $request->user()->id_pengguna,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deleted} data aktivitas sistem.",
                'deleted_count' => $deleted,
            ]);
        } catch (\Exception $e) {
            Log::error('[CLEANUP] Error menghapus aktivitas sistem', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus data log_aktivitas yang lebih lama dari tanggal yang ditentukan.
     * SUPER ADMIN ONLY.
     */
    public function cleanupLogAktivitas(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:1970|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bulan = (int) $request->get('bulan');
        $tahun = (int) $request->get('tahun');

        $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth();

        if ($akhirBulan->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus data masa depan.',
            ], 400);
        }

        try {
            // Hapus HANYA data pada bulan dan tahun yang dipilih
            $deleted = DB::table('log_aktivitas')
                ->whereBetween('waktu_kejadian', [$awalBulan, $akhirBulan])
                ->delete();

            Log::info('[CLEANUP] Log Aktivitas dibersihkan', [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'deleted_count' => $deleted,
                'user_id' => $request->user()->id_pengguna,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deleted} data log aktivitas.",
                'deleted_count' => $deleted,
            ]);
        } catch (\Exception $e) {
            Log::error('[CLEANUP] Error menghapus log aktivitas', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus data notifikasi yang lebih lama dari tanggal yang ditentukan.
     * SUPER ADMIN ONLY.
     */
    public function cleanupNotifikasi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:1970|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bulan = (int) $request->get('bulan');
        $tahun = (int) $request->get('tahun');

        $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth();

        if ($akhirBulan->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus data masa depan.',
            ], 400);
        }

        try {
            // Hapus HANYA data pada bulan dan tahun yang dipilih
            $deleted = DB::table('notifikasi')
                ->whereBetween('waktu_dibuat', [$awalBulan, $akhirBulan])
                ->delete();

            Log::info('[CLEANUP] Notifikasi dibersihkan', [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'deleted_count' => $deleted,
                'user_id' => $request->user()->id_pengguna,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deleted} notifikasi.",
                'deleted_count' => $deleted,
            ]);
        } catch (\Exception $e) {
            Log::error('[CLEANUP] Error menghapus notifikasi', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistik jumlah data untuk preview sebelum cleanup.
     */
    public function getCleanupStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:1970|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bulan = (int) $request->get('bulan');
        $tahun = (int) $request->get('tahun');

        // Buat range tanggal untuk bulan spesifik
        $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth();

        try {
            // Hitung HANYA data pada bulan dan tahun yang dipilih
            $countAktivitas = DB::table('aktivitas_sistem')
                ->whereBetween('waktu_kejadian', [$awalBulan, $akhirBulan])
                ->count();

            $countLogAktivitas = DB::table('log_aktivitas')
                ->whereBetween('waktu_kejadian', [$awalBulan, $akhirBulan])
                ->count();

            $countNotifikasi = DB::table('notifikasi')
                ->whereBetween('waktu_dibuat', [$awalBulan, $akhirBulan])
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'aktivitas_sistem' => $countAktivitas,
                    'log_aktivitas' => $countLogAktivitas,
                    'notifikasi' => $countNotifikasi,
                    'total' => $countAktivitas + $countLogAktivitas + $countNotifikasi,
                    'month' => $bulan,
                    'year' => $tahun,
                    'cutoff_date' => $akhirBulan->toDateString(),
                    'date_range' => $awalBulan->format('Y-m-d') . ' s/d ' . $akhirBulan->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus semua data (aktivitas, log, notifikasi) sekaligus.
     */
    public function cleanupAll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer|min:1970|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bulan = (int) $request->get('bulan');
        $tahun = (int) $request->get('tahun');

        $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth();

        if ($akhirBulan->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus data masa depan.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Hapus HANYA data pada bulan dan tahun yang dipilih
            $deletedAktivitas = DB::table('aktivitas_sistem')
                ->whereBetween('waktu_kejadian', [$awalBulan, $akhirBulan])
                ->delete();

            $deletedLog = DB::table('log_aktivitas')
                ->whereBetween('waktu_kejadian', [$awalBulan, $akhirBulan])
                ->delete();

            $deletedNotif = DB::table('notifikasi')
                ->whereBetween('waktu_dibuat', [$awalBulan, $akhirBulan])
                ->delete();

            DB::commit();

            $total = $deletedAktivitas + $deletedLog + $deletedNotif;

            Log::info('[CLEANUP] Cleanup semua data berhasil', [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'aktivitas_sistem' => $deletedAktivitas,
                'log_aktivitas' => $deletedLog,
                'notifikasi' => $deletedNotif,
                'total' => $total,
                'user_id' => $request->user()->id_pengguna,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus total {$total} data.",
                'data' => [
                    'aktivitas_sistem' => $deletedAktivitas,
                    'log_aktivitas' => $deletedLog,
                    'notifikasi' => $deletedNotif,
                    'total' => $total,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[CLEANUP] Error cleanup semua data', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
