<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\AktivitasSistem;
use Illuminate\Support\Facades\DB;

class AktivitasController extends Controller
{
    /**
     * [🔄 ACTIVITY_TRACKING] Tampilkan semua aktivitas dengan paginasi dan filter
     * Super Admin lihat semua, Admin hanya aktivitas sendiri
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // ✅ OPTIMASI: Gunakan JOIN untuk avoid N+1 problem
        // NOTE: aktivitas_sistem sudah di-split (2026_01_01_000006) dan memakai schema baru.
        $query = DB::table('aktivitas_sistem as a')
            ->leftJoin('pengguna as p', 'a.id_pengguna', '=', 'p.id_pengguna')
            ->select([
                'a.id_aktivitas',
                'a.id_pengguna',
                DB::raw('p.nama as nama_pengguna'),
                'a.aksi',
                'a.tabel_target',
                'a.id_target',
                'a.detail_perubahan',
                'a.keterangan',
                'a.alamat_ip',
                'a.user_agent',
                'a.waktu_kejadian',
                'p.foto as foto_profile',
                'p.email as email_pengguna',
                'p.peran as peran_pengguna'
            ])
            ->orderBy('a.waktu_kejadian', 'desc')
            ->orderBy('a.id_aktivitas', 'desc');

        // Super admin can see all activities
        // Admin can only see their own activities
        if ($user->peran === 'admin') {
            $query->where('a.id_pengguna', $user->id_pengguna);
        } elseif ($user->peran !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Super Admin dan Admin yang dapat melihat aktivitas.'
            ], 403);
        }

        // Filter by type (compatibility for frontend: login/create/edit/delete)
        if ($request->filled('tipe')) {
            $tipe = $request->string('tipe')->toString();

            $map = [
                'create' => ['tambah_proyek', 'tambah_pengguna'],
                'edit' => [
                    'ubah_proyek',
                    'ubah_pengguna',
                    'ubah_status_proyek',
                    'ubah_prioritas_proyek',
                    'ubah_status_ct',
                    'ubah_status_ut',
                    'ubah_rekap_boq',
                    'ubah_status_procurement',
                    'ubah_role_pengguna',
                    'reset_password_pengguna',
                    'bulk_update',
                ],
                'delete' => ['hapus_proyek', 'hapus_pengguna', 'bulk_delete', 'force_delete'],
                // 'login' berada di tabel log_aktivitas (bukan aktivitas_sistem)
                'login' => [],
            ];

            if (array_key_exists($tipe, $map)) {
                if (empty($map[$tipe])) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('a.aksi', $map[$tipe]);
                }
            }
        }

        // Filter by user if provided (only for super_admin)
        if ($request->filled('pengguna_id') && $user->peran === 'super_admin') {
            $query->where('a.id_pengguna', (int) $request->get('pengguna_id'));
        }

        // Filter by table if provided
        if ($request->filled('tabel')) {
            $query->where('a.tabel_target', $request->string('tabel')->toString());
        }

        // Search by user name, keterangan, aksi
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('p.nama', 'like', "%{$search}%")
                    ->orWhere('a.keterangan', 'like', "%{$search}%")
                    ->orWhere('a.aksi', 'like', "%{$search}%")
                    ->orWhere('a.tabel_target', 'like', "%{$search}%")
                    ->orWhere('a.id_target', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->filled('tanggal_mulai')) {
            $query->where('a.waktu_kejadian', '>=', $request->get('tanggal_mulai'));
        }

        if ($request->filled('tanggal_akhir')) {
            $query->where('a.waktu_kejadian', '<=', $request->get('tanggal_akhir') . ' 23:59:59');
        }

        $perPage = $request->get('per_page', 20);
        $activities = $query->paginate($perPage);

        // Format response dengan detail perubahan
        $formattedActivities = collect($activities->items())->map(function ($activity) {
            // Generate full URL for photo if exists
            if ($activity->foto_profile) {
                $activity->foto_profile = url('storage/' . $activity->foto_profile);
            }
            
            // Normalisasi detail_perubahan untuk frontend
            $detail = null;
            if (!empty($activity->detail_perubahan)) {
                $decoded = is_string($activity->detail_perubahan)
                    ? json_decode($activity->detail_perubahan, true)
                    : $activity->detail_perubahan;

                if (is_array($decoded)) {
                    $perubahan = [];
                    // Bisa berupa array of changes atau object; normalisasi ke array changes.
                    foreach ($decoded as $item) {
                        if (!is_array($item)) {
                            continue;
                        }

                        $field = $item['field'] ?? null;
                        if (!$field) {
                            continue;
                        }

                        $perubahan[] = [
                            'field' => $field,
                            'label' => $this->formatFieldName($field),
                            'nilai_lama' => $item['nilai_lama'] ?? null,
                            'nilai_baru' => $item['nilai_baru'] ?? null,
                        ];
                    }

                    $detail = ['perubahan' => $perubahan];
                }
            }

            $activity->detail_perubahan = $detail;
            
            return $activity;
        });

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
        
        // Load foto profile (generate full URL like ProfileController)
        if ($activity->pengguna) {
            $activity->foto_profile = $activity->pengguna->foto
                ? url('storage/' . $activity->pengguna->foto)
                : null;
        }

        // Normalisasi detail_perubahan
        $perubahan = [];
        if (is_array($activity->detail_perubahan)) {
            foreach ($activity->detail_perubahan as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $field = $item['field'] ?? null;
                if (!$field) {
                    continue;
                }
                $perubahan[] = [
                    'field' => $field,
                    'label' => $this->formatFieldName($field),
                    'nilai_lama' => $item['nilai_lama'] ?? null,
                    'nilai_baru' => $item['nilai_baru'] ?? null,
                ];
            }
        }

        $activity->detail_perubahan = ['perubahan' => $perubahan];

        return response()->json([
            'success' => true,
            'data' => $activity
        ]);
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

}
