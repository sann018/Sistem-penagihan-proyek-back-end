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
     * Resolve cleanup date range from request.
     *
     * Supported modes:
     * - day:    { mode: 'day', date: 'YYYY-MM-DD' }
     * - week:   { mode: 'week', date: 'YYYY-MM-DD' }  (week of the provided date)
     * - month:  { mode: 'month', bulan: 1-12, tahun: 1970-2100 } (default if mode omitted)
     * - year:   { mode: 'year', tahun: 1970-2100 }
     *
     * Backward compatible: if mode is omitted, it behaves like month with {bulan,tahun}.
     *
     * Returns: [mode, start, end, endRange]
     * - end: requested end of period
     * - endRange: bounded by now for current (partial) periods
     */
    private function resolveRange(Request $request): array
    {
        $mode = (string) ($request->get('mode') ?? 'month');
        $mode = strtolower(trim($mode));

        $validator = match ($mode) {
            'day', 'week' => Validator::make($request->all(), [
                'date' => 'required|date_format:Y-m-d',
            ]),
            'year' => Validator::make($request->all(), [
                'tahun' => 'required|integer|min:1970|max:2100',
            ]),
            'month' => Validator::make($request->all(), [
                'bulan' => 'required|integer|min:1|max:12',
                'tahun' => 'required|integer|min:1970|max:2100',
            ]),
            default => Validator::make([], []),
        };

        if (!in_array($mode, ['day', 'week', 'month', 'year'], true)) {
            throw new \InvalidArgumentException('Mode tidak valid. Gunakan day/week/month/year.');
        }

        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            throw new \InvalidArgumentException($messages[0] ?? 'Validasi gagal');
        }

        $now = Carbon::now();

        if ($mode === 'day') {
            $date = Carbon::createFromFormat('Y-m-d', (string) $request->get('date'));
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();
        } elseif ($mode === 'week') {
            $date = Carbon::createFromFormat('Y-m-d', (string) $request->get('date'));
            $start = $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $date->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        } elseif ($mode === 'year') {
            $tahun = (int) $request->get('tahun');
            $start = Carbon::create($tahun, 1, 1)->startOfYear();
            $end = Carbon::create($tahun, 1, 1)->endOfYear();
        } else { // month
            $bulan = (int) $request->get('bulan');
            $tahun = (int) $request->get('tahun');
            $start = Carbon::create($tahun, $bulan, 1)->startOfMonth();
            $end = Carbon::create($tahun, $bulan, 1)->endOfMonth();
        }

        // Block only if the chosen period starts in the future.
        if ($start->greaterThan($now)) {
            throw new \RuntimeException('Tidak dapat menghapus data masa depan.');
        }

        // Allow current partial period by bounding end to now.
        $endRange = $end->greaterThan($now) ? $now : $end;

        return [$mode, $start, $end, $endRange];
    }

    /**
     * Batch delete helper to avoid long locks/timeouts.
     */
    private function deleteInBatches(string $table, string $timeColumn, string $idColumn, Carbon $start, Carbon $endRange, int $batchSize = 5000): int
    {
        $totalDeleted = 0;

        while (true) {
            $ids = DB::table($table)
                ->whereBetween($timeColumn, [$start, $endRange])
                ->orderBy($idColumn)
                ->limit($batchSize)
                ->pluck($idColumn)
                ->all();

            if (empty($ids)) {
                break;
            }

            $deleted = DB::table($table)
                ->whereIn($idColumn, $ids)
                ->delete();

            $totalDeleted += $deleted;

            // Safety break (shouldn't happen, but prevents infinite loops)
            if ($deleted === 0) {
                break;
            }
        }

        return $totalDeleted;
    }

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
        try {
            [$mode, $awal, $akhir, $endRange] = $this->resolveRange($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => [$e->getMessage()],
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        try {
            $deleted = $this->deleteInBatches('aktivitas_sistem', 'waktu_kejadian', 'id_aktivitas', $awal, $endRange);

            Log::info('[CLEANUP] Aktivitas Sistem dibersihkan', [
                'mode' => $mode,
                'range_start' => $awal->toDateTimeString(),
                'range_end' => $endRange->toDateTimeString(),
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
        try {
            [$mode, $awal, $akhir, $endRange] = $this->resolveRange($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => [$e->getMessage()],
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        try {
            $deleted = $this->deleteInBatches('log_aktivitas', 'waktu_kejadian', 'id_log', $awal, $endRange);

            Log::info('[CLEANUP] Log Aktivitas dibersihkan', [
                'mode' => $mode,
                'range_start' => $awal->toDateTimeString(),
                'range_end' => $endRange->toDateTimeString(),
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
        try {
            [$mode, $awal, $akhir, $endRange] = $this->resolveRange($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => [$e->getMessage()],
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        try {
            $deleted = $this->deleteInBatches('notifikasi', 'waktu_dibuat', 'id_notifikasi', $awal, $endRange);

            Log::info('[CLEANUP] Notifikasi dibersihkan', [
                'mode' => $mode,
                'range_start' => $awal->toDateTimeString(),
                'range_end' => $endRange->toDateTimeString(),
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
        try {
            [$mode, $awal, $akhir, $endRange] = $this->resolveRange($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => [$e->getMessage()],
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        try {
            // Hitung data pada range yang dipilih
            $countAktivitas = DB::table('aktivitas_sistem')
                ->whereBetween('waktu_kejadian', [$awal, $endRange])
                ->count();

            $countLogAktivitas = DB::table('log_aktivitas')
                ->whereBetween('waktu_kejadian', [$awal, $endRange])
                ->count();

            $countNotifikasi = DB::table('notifikasi')
                ->whereBetween('waktu_dibuat', [$awal, $endRange])
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'aktivitas_sistem' => $countAktivitas,
                    'log_aktivitas' => $countLogAktivitas,
                    'notifikasi' => $countNotifikasi,
                    'total' => $countAktivitas + $countLogAktivitas + $countNotifikasi,
                    'mode' => $mode,
                    'cutoff_date' => $endRange->toDateString(),
                    'date_range' => $awal->format('Y-m-d') . ' s/d ' . $endRange->format('Y-m-d'),
                    'range_start' => $awal->toDateString(),
                    'range_end' => $endRange->toDateString(),
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
        try {
            [$mode, $awal, $akhir, $endRange] = $this->resolveRange($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => [$e->getMessage()],
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        DB::beginTransaction();

        try {
            $deletedAktivitas = $this->deleteInBatches('aktivitas_sistem', 'waktu_kejadian', 'id_aktivitas', $awal, $endRange);
            $deletedLog = $this->deleteInBatches('log_aktivitas', 'waktu_kejadian', 'id_log', $awal, $endRange);
            $deletedNotif = $this->deleteInBatches('notifikasi', 'waktu_dibuat', 'id_notifikasi', $awal, $endRange);

            DB::commit();

            $total = $deletedAktivitas + $deletedLog + $deletedNotif;

            Log::info('[CLEANUP] Cleanup semua data berhasil', [
                'mode' => $mode,
                'range_start' => $awal->toDateTimeString(),
                'range_end' => $endRange->toDateTimeString(),
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
