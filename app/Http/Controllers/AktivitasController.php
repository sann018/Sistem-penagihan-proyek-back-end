<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\AktivitasSistem;

class AktivitasController extends Controller
{
    /**
     * [🔄 ACTIVITY_TRACKING] Tampilkan semua aktivitas dengan paginasi dan filter
     * Super Admin lihat semua, Admin hanya aktivitas sendiri
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = AktivitasSistem::recent();

        // Super admin can see all activities
        // Admin can only see their own activities
        if ($user->peran === 'admin') {
            $query->byUser($user->id);
        } elseif ($user->peran !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin dan Admin yang dapat melihat aktivitas.'
            ], 403);
        }

        // Filter by type if provided
        if ($request->has('tipe')) {
            $query->byType($request->tipe);
        }

        // Filter by user if provided (only for super_admin)
        if ($request->has('pengguna_id') && $user->peran === 'super_admin') {
            $query->byUser($request->pengguna_id);
        }

        // Filter by table if provided
        if ($request->has('tabel')) {
            $query->byTable($request->tabel);
        }

        // Search by user name or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_pengguna', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%")
                  ->orWhere('aksi', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->has('tanggal_mulai')) {
            $query->where('waktu_aksi', '>=', $request->tanggal_mulai);
        }

        if ($request->has('tanggal_akhir')) {
            $query->where('waktu_aksi', '<=', $request->tanggal_akhir . ' 23:59:59');
        }

        $perPage = $request->get('per_page', 20);
        $activities = $query->paginate($perPage);

        // Format response dengan detail perubahan
        $formattedActivities = $activities->items();
        foreach ($formattedActivities as $activity) {
            // Load pengguna relationship untuk foto profile
            if ($activity->pengguna) {
                $activity->foto_profile = $activity->pengguna->foto ?? null;
                $activity->user_id = $activity->pengguna->id;
            }
            
            // Format detail perubahan dari data_sebelum dan data_sesudah
            if ($activity->data_sebelum && $activity->data_sesudah) {
                $activity->perubahan_detail = $this->formatDetailPerubahan(
                    $activity->data_sebelum,
                    $activity->data_sesudah,
                    $activity->tabel_yang_diubah
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => $formattedActivities,
            'pagination' => [
                'total' => $activities->total(),
                'per_page' => $activities->perPage(),
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
            ]
        ]);
    }

    /**
     * Get activity details
     */
    public function show($id, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->peran !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak.'
            ], 403);
        }

        $activity = AktivitasSistem::findOrFail($id);
        
        // Load foto profile
        if ($activity->pengguna) {
            $activity->foto_profile = $activity->pengguna->foto ?? null;
        }
        
        // Format detail perubahan
        if ($activity->data_sebelum && $activity->data_sesudah) {
            $activity->perubahan_detail = $this->formatDetailPerubahan(
                $activity->data_sebelum,
                $activity->data_sesudah,
                $activity->tabel_yang_diubah
            );
        }

        return response()->json([
            'success' => true,
            'data' => $activity
        ]);
    }

    /**
     * Format detail perubahan dari data sebelum dan sesudah
     * Membandingkan setiap field dan menampilkan perubahan yang terjadi
     */
    private function formatDetailPerubahan($dataSebelum, $dataSesudah, $tabelYangDiubah): array
    {
        $perubahan = [];
        
        // Merge semua keys dari kedua array
        $allKeys = array_merge(
            array_keys((array)$dataSebelum),
            array_keys((array)$dataSesudah)
        );
        $allKeys = array_unique($allKeys);

        foreach ($allKeys as $key) {
            $nilaiLama = $dataSebelum[$key] ?? null;
            $nilaiBaru = $dataSesudah[$key] ?? null;

            // Hanya tampilkan jika ada perubahan
            if ($nilaiLama !== $nilaiBaru) {
                $namaField = $this->formatFieldName($key);
                $perubahan[$key] = [
                    'nama_field' => $namaField,
                    'nilai_lama' => $nilaiLama,
                    'nilai_baru' => $nilaiBaru,
                ];
            }
        }

        return $perubahan;
    }

    /**
     * Format nama field dari snake_case menjadi readable format
     * Contoh: status_ct -> Status CT
     */
    private function formatFieldName($fieldName): string
    {
        // Replace underscore dengan spasi
        $formatted = str_replace('_', ' ', $fieldName);
        
        // Capitalize each word
        $formatted = ucwords(strtolower($formatted));
        
        // Special cases untuk field yang penting
        $specialCases = [
            'Status CT' => 'Status CT',
            'Status UT' => 'Status UT',
            'Rekon Nilai' => 'Rekon Nilai',
            'Rekon Ppn' => 'Rekon PPN',
            'Rekap BOQ' => 'Rekap BOQ',
            'Fase' => 'Fase',
            'PID' => 'PID',
            'PO' => 'PO',
        ];
        
        foreach ($specialCases as $original => $replacement) {
            if (strtolower($formatted) === strtolower($original)) {
                return $replacement;
            }
        }
        
        return $formatted;
    }

    /**
     * Log activity - Called internally by application
     * This is a static helper method that can be called from anywhere
     */
    public static function logActivity(
        $pengguna,
        $aksi,
        $tipe,
        $deskripsi,
        $tabelYangDiubah = null,
        $idRecordYangDiubah = null,
        $dataSebelum = null,
        $dataSesudah = null,
        $ipAddress = null,
        $userAgent = null
    ): AktivitasSistem {
        return AktivitasSistem::create([
            'pengguna_id' => $pengguna->id,
            'nama_pengguna' => $pengguna->nama,
            'aksi' => $aksi,
            'tipe' => $tipe,
            'deskripsi' => $deskripsi,
            'tabel_yang_diubah' => $tabelYangDiubah,
            'id_record_yang_diubah' => $idRecordYangDiubah,
            'data_sebelum' => $dataSebelum ? json_encode($dataSebelum) : null,
            'data_sesudah' => $dataSesudah ? json_encode($dataSesudah) : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'waktu_aksi' => now(),
        ]);
    }
}
