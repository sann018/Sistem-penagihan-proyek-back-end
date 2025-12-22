<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\AktivitasSistem;

class AktivitasController extends Controller
{
    /**
     * Get all activities with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Check permission - only super_admin can see all activities
        if ($user->peran !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin yang dapat melihat aktivitas sistem.'
            ], 403);
        }

        $query = AktivitasSistem::recent();

        // Filter by type if provided
        if ($request->has('tipe')) {
            $query->byType($request->tipe);
        }

        // Filter by user if provided
        if ($request->has('pengguna_id')) {
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

        return response()->json([
            'success' => true,
            'data' => $activities->items(),
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

        return response()->json([
            'success' => true,
            'data' => $activity
        ]);
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
