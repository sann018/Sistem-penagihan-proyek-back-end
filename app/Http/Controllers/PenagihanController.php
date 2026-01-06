<?php

namespace App\Http\Controllers;

use App\Models\Penagihan;
use App\Services\PriorityService;
use App\Enums\ProjectPriorityLevel;
use App\Enums\ProjectPrioritySource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\InvoicesImport;
use App\Exports\InvoicesExport;
use App\Exports\InvoicesTemplateExport;
use App\Traits\LogsActivity;

class PenagihanController extends Controller
{
    use LogsActivity;
    /**
     * [📊 PROJECT_MANAGEMENT] Tampilkan list semua project penagihan
     * Mendukung search, filter status, card filter, sorting, dan PRIORITAS
     * 
     * UPGRADE V2:
     * - dashboard=true: Tampilkan hanya proyek prioritas (1 dan 2) untuk dashboard
     * - Proyek prioritas 1 (manual) muncul duluan, kemudian prioritas 2 (auto)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Penagihan::query();

        // FITUR BARU: Filter untuk dashboard (hanya prioritas)
        if ($request->boolean('dashboard')) {
            // Dashboard menggunakan prioritas legacy (1/2/3) yang di-set oleh user
            $query->whereIn('prioritas', [1, 2, 3])
                ->orderBy('prioritas', 'asc')
                ->orderBy('prioritas_updated_at', 'desc')
                ->orderBy('dibuat_pada', 'desc')
                ->orderBy('pid', 'asc');
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_proyek', 'like', "%{$search}%")
                  ->orWhere('nama_mitra', 'like', "%{$search}%")
                  ->orWhere('pid', 'like', "%{$search}%")
                  ->orWhere('nomor_po', 'like', "%{$search}%");
            });
        }

        // Filter by card status (ketika user klik card)
        if ($request->has('card_filter')) {
            $cardFilter = $request->card_filter;
            
            switch ($cardFilter) {
                case 'sudah_penuh':
                    // Semua 6 status dropdown = selesai/hijau (case insensitive)
                    $query->whereRaw('LOWER(status_ct) = ?', ['sudah ct'])
                          ->whereRaw('LOWER(status_ut) = ?', ['sudah ut'])
                          ->whereRaw('LOWER(rekap_boq) = ?', ['sudah rekap'])
                          ->whereRaw('LOWER(rekon_material) = ?', ['sudah rekon'])
                          ->whereRaw('LOWER(pelurusan_material) = ?', ['sudah lurus'])
                          ->whereRaw('LOWER(status_procurement) = ?', ['otw reg']);
                    break;
                    
                case 'tertunda':
                    // Status Procurement = Revisi Mitra
                    $query->whereRaw('LOWER(status_procurement) = ?', ['revisi mitra']);
                    break;
                    
                case 'belum_rekon':
                    // Rekap BOQ = Belum Rekap
                    $query->whereRaw('LOWER(rekap_boq) = ?', ['belum rekap']);
                    break;
                    
                case 'sedang_berjalan':
                    // Ada salah satu status yang belum selesai
                    $query->where(function($q) {
                        $q->whereRaw('LOWER(status_ct) != ?', ['sudah ct'])
                          ->orWhereRaw('LOWER(status_ut) != ?', ['sudah ut'])
                          ->orWhereRaw('LOWER(rekap_boq) != ?', ['sudah rekap'])
                          ->orWhereRaw('LOWER(rekon_material) != ?', ['sudah rekon'])
                          ->orWhereRaw('LOWER(pelurusan_material) != ?', ['sudah lurus'])
                          ->orWhereRaw('LOWER(status_procurement) != ?', ['otw reg']);
                    })
                    ->whereRaw('LOWER(status_procurement) != ?', ['revisi mitra']);
                    break;
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // ✅ OPTIMASI: Sorting dengan ORDER BY konsisten untuk pagination
        if (!$request->boolean('dashboard')) {
            $sortBy = $request->get('sort_by', 'prioritas');
            $sortOrder = $request->get('sort_order', 'desc');
            
            // Primary sort
            $query->orderBy($sortBy, $sortOrder);
            
            // ✅ Tambah secondary sort untuk konsistensi (tiebreaker)
            if ($sortBy !== 'dibuat_pada') {
                $query->orderBy('dibuat_pada', 'desc');
            }
            
            // ✅ Final tiebreaker dengan PK untuk hasil yang 100% konsisten
            $query->orderBy('pid', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $penagihan = $query->paginate($perPage);

        // Add timer info dan prioritas info untuk setiap project
        $penagihan->getCollection()->transform(function ($item) {
            $item = $this->addTimerInfo($item);
            $item->prioritas_label = $this->getPrioritasLabel($item->prioritas);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $penagihan
        ]);
    }

    /**
     * Helper: Get prioritas label
     */
    private function getPrioritasLabel(?int $prioritas): ?string
    {
        return match ($prioritas) {
            1 => 'Prioritas 1',
            2 => 'Prioritas 2',
            3 => 'Prioritas 3',
            default => null,
        };
    }

    /**
     * Add timer countdown info ke project
     * Menampilkan sisa waktu atau waktu yang sudah terlewat
     */
    private function addTimerInfo($penagihan)
    {
        if (!$penagihan->tanggal_mulai || !$penagihan->estimasi_durasi_hari) {
            return $penagihan;
        }

        $startDate = \Carbon\Carbon::parse($penagihan->tanggal_mulai);
        $deadline = $startDate->copy()->addDays($penagihan->estimasi_durasi_hari);
        $now = now();

        $isOverdue = $now->greaterThan($deadline);

        if ($isOverdue) {
            // Hitung waktu yang sudah terlewat (dalam nilai positif)
            $totalSecondOverdue = $now->diffInSeconds($deadline);
            $overdueDays = intval($totalSecondOverdue / (86400)); // 86400 = 24*60*60
            $overdueHours = intval(($totalSecondOverdue % 86400) / 3600);
            $overdueMinutes = intval(($totalSecondOverdue % 3600) / 60);
            $overdueSeconds = intval($totalSecondOverdue % 60);
            
            $timerStatus = 'overdue';
            $displayTime = '-' . $overdueDays . 'h -' . $overdueHours . 'j -' . $overdueMinutes . 'm -' . $overdueSeconds . 'd (Melewati Batas)';
            $daysRemaining = -$overdueDays;
        } else {
            // Hitung sisa waktu
            $daysRemaining = $deadline->diffInDays($now);
            $remainingTime = $deadline->diffInSeconds($now);
            $hours = intval($remainingTime / 3600) % 24;
            $minutes = intval($remainingTime / 60) % 60;
            
            // Tentukan status berdasarkan sisa waktu
            if ($daysRemaining <= 2) {
                $timerStatus = 'danger'; // Merah - kurang dari 3 hari
            } elseif ($daysRemaining <= 10) {
                $timerStatus = 'warning'; // Kuning - antara 3-10 hari
            } else {
                $timerStatus = 'normal'; // Biru - lebih dari 10 hari
            }
            
            $displayTime = $daysRemaining . ' hari ' . $hours . ' jam ' . $minutes . ' menit';
        }

        // Add timer info ke object
        $penagihan->timer = [
            'display' => $displayTime,
            'status' => $timerStatus, // 'normal' (biru), 'warning' (kuning), 'danger' (merah), 'overdue' (merah blink)
            'days_remaining' => $daysRemaining,
            'deadline' => $deadline->toDateString(),
            'is_overdue' => $isOverdue
        ];
        
        // Add priority info with enum values
        if ($penagihan->priority_level) {
            $levelEnum = $penagihan->getPriorityLevelEnum();
            $sourceEnum = $penagihan->getPrioritySourceEnum();
            
            $penagihan->priority_info = [
                'level' => $penagihan->priority_level,
                'level_label' => $levelEnum ? $levelEnum->label() : 'Tidak Ada',
                'level_icon' => $levelEnum ? $levelEnum->icon() : '⚪',
                'level_color' => $levelEnum ? $levelEnum->colorClass() : 'text-gray-700 bg-gray-100 border-gray-300',
                'source' => $penagihan->priority_source,
                'source_label' => $sourceEnum ? $sourceEnum->label() : null,
                'can_override' => $sourceEnum ? $sourceEnum->canOverride() : true,
                'score' => $penagihan->priority_score ?? 0,
                'reason' => $penagihan->priority_reason,
                'is_high_priority' => $penagihan->isHighPriority(),
                'is_critical' => $penagihan->isCritical(),
            ];
        } else {
            $penagihan->priority_info = [
                'level' => 'none',
                'level_label' => 'Tidak Ada',
                'level_icon' => '⚪',
                'level_color' => 'bg-gray-300 text-gray-700',
                'source' => null,
                'source_label' => null,
                'can_override' => true,
                'score' => 0,
                'reason' => null,
                'is_high_priority' => false,
                'is_critical' => false,
            ];
        }

        return $penagihan;
    }

    /**
     * [📊 PROJECT_MANAGEMENT] Buat project penagihan baru
     * Validasi semua field yang diperlukan
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();
        if (array_key_exists('nomor_po', $payload)) {
            $payload['nomor_po'] = $payload['nomor_po'] === null ? null : trim((string) $payload['nomor_po']);
            if ($payload['nomor_po'] === '') {
                $payload['nomor_po'] = null;
            }
        }

        $validator = Validator::make($payload, [
            'nama_proyek' => 'required|string|max:255',
            'nama_mitra' => 'required|string|max:255',
            'pid' => 'required|string|unique:data_proyek,pid',
            'jenis_po' => 'nullable|string|max:255',
            'nomor_po' => 'nullable|string|max:255|unique:data_proyek,nomor_po',
            'phase' => 'required|string|max:255',
            'rekon_nilai' => 'required|numeric|min:0',
            'status_ct' => 'nullable|string|max:255',
            'status_ut' => 'nullable|string|max:255',
            'rekap_boq' => 'nullable|string|max:255',
            'rekon_material' => 'nullable|string|max:255',
            'pelurusan_material' => 'nullable|string|max:255',
            'status_procurement' => 'nullable|string|max:255',
            'estimasi_durasi_hari' => 'nullable|integer|min:1',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_invoice' => 'nullable|date',
            'tanggal_jatuh_tempo' => 'nullable|date|after_or_equal:tanggal_invoice',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prepare data
        $data = $payload;
        
        // Auto-set timer ke 30 hari jika tidak ada input
        if (empty($data['estimasi_durasi_hari'])) {
            $data['estimasi_durasi_hari'] = 30;
        }
        if (empty($data['tanggal_mulai'])) {
            $data['tanggal_mulai'] = now()->toDateString();
        }

        $penagihan = Penagihan::create($data);

        // Log activity
        $this->logActivity(
            $request,
            'Tambah Proyek',
            'create',
            "Menambahkan proyek baru: {$penagihan->nama_proyek}",
            'penagihan',
            $penagihan->pid,
            null,
            $this->sanitizeDataForLog($penagihan->toArray())
        );

        return response()->json([
            'success' => true,
            'message' => 'Penagihan berhasil dibuat',
            'data' => $this->addTimerInfo($penagihan)
        ], 201);
    }

    /**
     * [📊 PROJECT_MANAGEMENT] Tampilkan detail project penagihan berdasarkan ID
     */
    public function show(string $id): JsonResponse
    {
        $penagihan = Penagihan::find($id);

        if (!$penagihan) {
            return response()->json([
                'success' => false,
                'message' => 'Penagihan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->addTimerInfo($penagihan)
        ]);
    }

    /**
     * [📊 PROJECT_MANAGEMENT] Update data project penagihan
     * Menyimpan perubahan sebelum dan sesudah untuk audit trail
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $penagihan = Penagihan::find($id);

        if (!$penagihan) {
            return response()->json([
                'success' => false,
                'message' => 'Penagihan tidak ditemukan'
            ], 404);
        }

        $payload = $request->all();
        if (array_key_exists('nomor_po', $payload)) {
            $payload['nomor_po'] = $payload['nomor_po'] === null ? null : trim((string) $payload['nomor_po']);
            if ($payload['nomor_po'] === '') {
                $payload['nomor_po'] = null;
            }
        }

        $validator = Validator::make($payload, [
            'nama_proyek' => 'sometimes|required|string|max:255',
            'nama_mitra' => 'sometimes|required|string|max:255',
            'pid' => 'sometimes|required|string|unique:data_proyek,pid,' . $id . ',pid',
            'jenis_po' => 'nullable|string|max:255',
            'nomor_po' => 'sometimes|nullable|string|max:255|unique:data_proyek,nomor_po,' . $id . ',pid',
            'phase' => 'sometimes|required|string|max:255',
            'rekon_nilai' => 'sometimes|required|numeric|min:0',
            'status_ct' => 'nullable|string|max:255',
            'status_ut' => 'nullable|string|max:255',
            'rekap_boq' => 'nullable|string|max:255',
            'rekon_material' => 'nullable|string|max:255',
            'pelurusan_material' => 'nullable|string|max:255',
            'status_procurement' => 'nullable|string|max:255',
            'estimasi_durasi_hari' => 'nullable|integer|min:1',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_invoice' => 'nullable|date',
            'tanggal_jatuh_tempo' => 'nullable|date|after_or_equal:tanggal_invoice',
            'catatan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // =====================================================
        // Auto Revisi Mitra
        // Trigger jika:
        // 1) proyek sudah di tahap procurement lanjut (Sekuler/Scan/OTW), ATAU
        // 2) proyek sebelumnya sudah memenuhi seluruh proses prasyarat selesai
        //    (CT/UT/BOQ/Rekon Material/Pelurusan Material) meskipun procurement
        //    masih Proses Periv.
        // Lalu ada perubahan pada proses: CT/UT/BOQ/Rekon Nilai/Rekon Material/
        // Pelurusan Material.
        // =====================================================
        $currentProcurement = strtolower(trim((string) ($penagihan->status_procurement ?? '')));
        $advancedProcurements = ['sekuler ttd', 'scan dokumen mitra', 'otw reg'];

        $wasPrerequisitesDone = (
            strtolower(trim((string) ($penagihan->status_ct ?? ''))) === 'sudah ct' &&
            strtolower(trim((string) ($penagihan->status_ut ?? ''))) === 'sudah ut' &&
            strtolower(trim((string) ($penagihan->rekap_boq ?? ''))) === 'sudah rekap' &&
            strtolower(trim((string) ($penagihan->rekon_material ?? ''))) === 'sudah rekon' &&
            strtolower(trim((string) ($penagihan->pelurusan_material ?? ''))) === 'sudah lurus'
        );

        if (in_array($currentProcurement, $advancedProcurements, true) || $wasPrerequisitesDone) {
            $revisionFields = [
                'status_ct',
                'status_ut',
                'rekap_boq',
                'rekon_nilai',
                'rekon_material',
                'pelurusan_material',
            ];

            $hasRevisionChange = false;
            foreach ($revisionFields as $field) {
                if (!$request->has($field)) {
                    continue;
                }

                $oldValue = $penagihan->{$field};
                $newValue = $request->input($field);

                if ($field === 'rekon_nilai') {
                    // Compare as numeric string (avoid locale formatting issues)
                    $oldNum = is_null($oldValue) ? null : (string) (0 + $oldValue);
                    $newNum = is_null($newValue) ? null : (string) (0 + $newValue);
                    if ($oldNum !== $newNum) {
                        $hasRevisionChange = true;
                        break;
                    }
                    continue;
                }

                $oldStr = strtolower(trim((string) ($oldValue ?? '')));
                $newStr = strtolower(trim((string) ($newValue ?? '')));
                if ($oldStr !== $newStr) {
                    $hasRevisionChange = true;
                    break;
                }
            }

            if ($hasRevisionChange && $currentProcurement !== 'revisi mitra') {
                $request->merge(['status_procurement' => 'Revisi Mitra']);
            }
        }

        // =====================================================
        // Business rule: procurement advanced statuses require
        // all previous processes completed.
        // - Disallow selecting: Sekuler TTD, Scan Dokumen Mitra, OTW Reg
        //   until: CT, UT, Rekap BOQ, Rekon Material, Pelurusan Material are done.
        // =====================================================
        if ($request->has('status_procurement')) {
            $newProcurement = strtolower(trim((string) $request->input('status_procurement', '')));
            $restricted = ['sekuler ttd', 'scan dokumen mitra', 'otw reg'];

            if (in_array($newProcurement, $restricted, true)) {
                // Evaluate prerequisites using merged (existing + incoming) values
                $statusCt = strtolower(trim((string) $request->input('status_ct', $penagihan->status_ct ?? '')));
                $statusUt = strtolower(trim((string) $request->input('status_ut', $penagihan->status_ut ?? '')));
                $rekapBoq = strtolower(trim((string) $request->input('rekap_boq', $penagihan->rekap_boq ?? '')));
                $rekonMaterial = strtolower(trim((string) $request->input('rekon_material', $penagihan->rekon_material ?? '')));
                $pelurusanMaterial = strtolower(trim((string) $request->input('pelurusan_material', $penagihan->pelurusan_material ?? '')));

                $prerequisitesDone = (
                    $statusCt === 'sudah ct' &&
                    $statusUt === 'sudah ut' &&
                    $rekapBoq === 'sudah rekap' &&
                    $rekonMaterial === 'sudah rekon' &&
                    $pelurusanMaterial === 'sudah lurus'
                );

                if (!$prerequisitesDone) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak bisa memilih status procurement tersebut sebelum Status CT, Status UT, Rekap BOQ, Rekon Material, dan Pelurusan Material dinyatakan selesai.',
                    ], 422);
                }
            }
        }

        // Store old data for audit
        $dataSebelum = $this->sanitizeDataForLog($penagihan->toArray());
        
        $penagihan->update($request->all());
        
        // Store new data for audit
        $dataSesudah = $this->sanitizeDataForLog($penagihan->fresh()->toArray());

        // Log activity
        $this->logActivity(
            $request,
            'Edit Proyek',
            'edit',
            "Mengubah data proyek: {$penagihan->nama_proyek}",
            'penagihan',
            $penagihan->pid,
            $dataSebelum,
            $dataSesudah
        );

        return response()->json([
            'success' => true,
            'message' => 'Penagihan berhasil diupdate',
            'data' => $penagihan
        ]);
    }

    /**
     * [📊 PROJECT_MANAGEMENT] Hapus project penagihan dan log aktivitasnya
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $penagihan = Penagihan::find($id);

        if (!$penagihan) {
            return response()->json([
                'success' => false,
                'message' => 'Penagihan tidak ditemukan'
            ], 404);
        }

        // Store data before delete for audit
        $dataSebelum = $this->sanitizeDataForLog($penagihan->toArray());
        $namaProyek = $penagihan->nama_proyek;
        
        $penagihan->delete();

        // Log activity
        $this->logActivity(
            $request,
            'Hapus Proyek',
            'delete',
            "Menghapus proyek: {$namaProyek}",
            'penagihan',
            $id,
            $dataSebelum,
            null
        );

        return response()->json([
            'success' => true,
            'message' => 'Penagihan berhasil dihapus'
        ]);
    }

    /**
     * [📄 PROJECT_MANAGEMENT] Hitung statistik dashboard penagihan
     * Total invoice, jumlah uang, status pembayaran, dll
     */
    /**
     * [📊 PROJECT_STATISTICS] Get card statistics berdasarkan status dropdown
     * 
     * UPGRADE V2 - REQUIREMENT BARU:
     * - Sudah Penuh: SEMUA 6 kondisi HARUS terpenuhi:
     *   1. Status CT = "Sudah CT"
     *   2. Status UT = "Sudah UT"
     *   3. Rekap BOQ = "Sudah Rekap"
     *   4. Rekon Material = "Sudah Rekon"
     *   5. Pelurusan Material = "Sudah Lurus"
     *   6. Status Procurement = "OTW Reg"
     * - Sedang Berjalan: Ada status dropdown yang belum selesai
     * - Tertunda: Status Procurement = "Revisi Mitra"
     * - Belum Rekon: Rekap BOQ = "Belum Rekap"
     */
    public function cardStatistics(): JsonResponse
    {
        // Count Sudah Penuh (SEMUA 6 status HARUS match - case insensitive)
        $sudahPenuh = Penagihan::whereRaw('LOWER(status_ct) = ?', ['sudah ct'])
            ->whereRaw('LOWER(status_ut) = ?', ['sudah ut'])
            ->whereRaw('LOWER(rekap_boq) = ?', ['sudah rekap'])
            ->whereRaw('LOWER(rekon_material) = ?', ['sudah rekon'])
            ->whereRaw('LOWER(pelurusan_material) = ?', ['sudah lurus'])
            ->whereRaw('LOWER(status_procurement) = ?', ['otw reg'])
            ->count();

        // Count Tertunda (Status Procurement = Revisi Mitra - case insensitive)
        $tertunda = Penagihan::whereRaw('LOWER(status_procurement) = ?', ['revisi mitra'])->count();

        // Count Belum Rekon (Rekap BOQ = Belum Rekap - case insensitive)
        $belumRekon = Penagihan::whereRaw('LOWER(rekap_boq) = ?', ['belum rekap'])->count();

        // Count Sedang Berjalan (Total - Sudah Penuh - Tertunda)
        $totalProyek = Penagihan::count();
        $sedangBerjalan = $totalProyek - $sudahPenuh - $tertunda;

        // Ensure no negative values
        $sedangBerjalan = max(0, $sedangBerjalan);

        return response()->json([
            'success' => true,
            'data' => [
                'sudah_penuh' => $sudahPenuh,
                'sedang_berjalan' => $sedangBerjalan,
                'tertunda' => $tertunda,
                'belum_rekon' => $belumRekon,
                'total_proyek' => $totalProyek,
                'completion_percentage' => $totalProyek > 0 ? round(($sudahPenuh / $totalProyek) * 100, 2) : 0
            ]
        ]);
    }

    public function statistics(): JsonResponse
    {
        $totalInvoices = Penagihan::count();
        $totalAmount = Penagihan::sum('rekon_nilai');
        $totalPaid = Penagihan::where('status', 'dibayar')->sum('rekon_nilai');
        $totalPending = Penagihan::where('status', 'pending')->count();
        $totalOverdue = Penagihan::where('status', 'terlambat')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_invoices' => $totalInvoices,
                'total_amount' => $totalAmount,
                'total_paid' => $totalPaid,
                'total_pending' => $totalPending,
                'total_overdue' => $totalOverdue,
            ]
        ]);
    }

    /**
     * Set/unset prioritas proyek (1, 2, 3 atau null untuk hapus)
     */
    public function setPrioritize(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prioritas' => 'nullable|integer|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $penagihan = Penagihan::find($id);

        if (!$penagihan) {
            return response()->json([
                'success' => false,
                'message' => 'Proyek tidak ditemukan'
            ], 404);
        }

        $oldPrioritas = $penagihan->prioritas;
        $newPrioritas = $request->prioritas;
        
        // Update prioritas
        $penagihan->prioritas = $newPrioritas;
        $penagihan->prioritas_updated_at = now();
        $penagihan->save();
        
        // Log activity
        if ($newPrioritas === null) {
            $this->logActivity(
                $request,
                'Hapus Prioritas',
                'update',
                "Menghapus prioritas proyek '{$penagihan->nama_proyek}'",
                'penagihan',
                $penagihan->pid,
                ['prioritas' => $oldPrioritas],
                ['prioritas' => null]
            );
            
            $message = 'Prioritas berhasil dihapus';
        } else {
            $this->logActivity(
                $request,
                "Set Prioritas {$newPrioritas}",
                'update',
                "Mengubah prioritas proyek '{$penagihan->nama_proyek}' menjadi Prioritas {$newPrioritas}",
                'penagihan',
                $penagihan->pid,
                ['prioritas' => $oldPrioritas],
                ['prioritas' => $newPrioritas]
            );
            
            $message = "Proyek berhasil di-set sebagai Prioritas {$newPrioritas}";
        }

        $freshData = $penagihan->fresh();
        $freshData->prioritas_label = $this->getPrioritasLabel($freshData->prioritas);
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->addTimerInfo($freshData)
        ]);
    }

    /**
     * [🎯 PRIORITY_SYSTEM V2] Auto-prioritize proyek menggunakan smart multi-factor analysis
     * Menggunakan PriorityService dengan scoring system (deadline, progress, blocked, phase)
     */
    public function autoPrioritize(): JsonResponse
    {
        try {
            $priorityService = app(PriorityService::class);
            
            // Recalculate all pending projects
            $stats = $priorityService->recalculateAll();

            Log::info("Auto-prioritize V2 completed", $stats);

            return response()->json([
                'success' => true,
                'message' => 'Auto-prioritize berhasil dijalankan dengan sistem baru (multi-factor analysis)',
                'data' => [
                    'total_projects' => $stats['total'],
                    'updated' => $stats['updated'],
                    'skipped_manual' => $stats['skipped_manual'],
                    'analysis_factors' => [
                        'deadline_proximity',
                        'progress_gap',
                        'stuck_blocked',
                        'early_phase'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Auto-prioritize V2 error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menjalankan auto-prioritize',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * [📄 PROJECT_MANAGEMENT] [📑 EXCEL_OPERATIONS] Import data penagihan dari file Excel
     * Mendukung format xlsx, xls, csv dengan validasi dan error handling
     */
    public function import(Request $request): JsonResponse
    {
        // Validasi file upload
        $validator = Validator::make(
            $request->all(),
            [
                'file' => 'required|mimes:xlsx,xls,csv|max:10240', // Max 10MB
            ],
            [
                'file.required' => 'File wajib diupload',
                'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv',
                'file.max' => 'Ukuran file maksimal 10MB',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi file gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // [📤 EXCEL_OPERATIONS] HITUNG DATA SEBELUM IMPORT
            $beforeCount = Penagihan::count();
            
            /** @var InvoicesImport $import */
            $import = new InvoicesImport();
            
            // [📤 EXCEL_OPERATIONS] IMPORT FILE EXCEL
            Excel::import($import, $request->file('file'));

            // [📤 EXCEL_OPERATIONS] HITUNG DATA SETELAH IMPORT
            $afterCount = Penagihan::count();
            
            // [📤 EXCEL_OPERATIONS] HITUNG JUMLAH DATA YANG BERHASIL DIIMPORT
            $importedCount = $afterCount - $beforeCount;
            
            Log::info("Import Excel: $beforeCount records before, $afterCount after, $importedCount imported");
            Log::info("Import success count: " . $import->getSuccessCount() . ", row count: " . $import->getRowCount());

            // Get validation failures
            $failures = $import->failures();
            
            if (!empty($failures)) {
                $errors = [];
                $detailedErrors = [];
                
                /** @var \Maatwebsite\Excel\Validators\Failure $failure */
                foreach ($failures as $failure) {
                    $errors[] = [
                        'row' => $failure->row(),
                        'attribute' => $failure->attribute(),
                        'errors' => $failure->errors(),
                        'values' => $failure->values()
                    ];

                    $values = $failure->values();
                    $attr = $failure->attribute();
                    $detailLines = [];
                    foreach ($failure->errors() as $msg) {
                        $detailLines[] = ($attr ? "{$attr}: " : '') . $msg;
                    }

                    $detailedErrors[] = [
                        'row' => $failure->row(),
                        'error' => 'Validasi kolom',
                        'details' => $detailLines,
                        'data_preview' => [
                            'pid' => $values['pid'] ?? '',
                            'nama_proyek' => $values['nama_proyek'] ?? '',
                            'nama_mitra' => $values['nama_mitra'] ?? '',
                            'phase' => $values['phase'] ?? '',
                        ],
                    ];
                }

                // [📤 EXCEL_OPERATIONS] HITUNG FAILURE COUNT
                $failureCount = count($failures);
                
                Log::error("Import Excel validation failures: " . json_encode($errors));

                $invalidHeaders = method_exists($import, 'getInvalidHeaders') ? $import->getInvalidHeaders() : [];
                $errorDetails = [
                    'total_rows_processed' => $import->getRowCount(),
                    'detailed_errors' => $detailedErrors,
                    'invalid_headers' => $invalidHeaders,
                    'expected_headers' => \App\Imports\InvoicesImport::getExpectedHeaders(),
                    'has_valid_data' => $import->hasValidData(),
                ];

                $statusCode = $importedCount === 0 ? 400 : 200;
                $successFlag = $importedCount > 0;

                return response()->json([
                    'success' => $successFlag,
                    'message' => $importedCount === 0
                        ? "Import gagal: 0 berhasil, $failureCount gagal"
                        : "Import selesai: $importedCount berhasil, $failureCount gagal",
                    'warnings' => [
                        $failureCount . ' baris gagal karena validasi (kolom wajib kosong/format salah)'
                    ],
                    'success_count' => $importedCount,
                    'failed_count' => $failureCount,
                    'detailed_errors' => $detailedErrors,
                    'validation_details' => $errorDetails,
                ], $statusCode);
            }

            // Get import errors (parsing errors)
            $importErrors = $import->getErrors();
            if (!empty($importErrors)) {
                Log::error("Import Excel errors: " . json_encode($importErrors));
                
                return response()->json([
                    'success' => false,
                    'message' => "Import gagal: " . implode(', ', $importErrors),
                    'success_count' => $importedCount,
                    'errors' => $importErrors
                ], 400);
            }

            // [📤 EXCEL_OPERATIONS] COLLECT DETAILED VALIDATION INFO
            $duplicatePids = $import->getDuplicatePids();
            $detailedErrors = $import->getDetailedErrors();
            $hasValidData = $import->hasValidData();
            $invalidHeaders = $import->getInvalidHeaders();
            
            // [📤 EXCEL_OPERATIONS] NO DATA IMPORTED - PROVIDE DETAILED ERROR
            if ($importedCount === 0) {
                $errorDetails = [
                    'total_rows_processed' => $import->getRowCount(),
                    'duplicate_pids' => $duplicatePids,
                    'detailed_errors' => $detailedErrors,
                    'has_valid_data' => $hasValidData,
                    'invalid_headers' => $invalidHeaders,
                    'expected_headers' => \App\Imports\InvoicesImport::getExpectedHeaders(),
                ];
                
                // Determine main error reason
                $mainError = 'Tidak ada data valid yang dapat diimport.';
                $suggestions = [];
                
                if (!empty($invalidHeaders)) {
                    $mainError = 'Format header Excel tidak sesuai.';
                    $suggestions[] = '❌ Header tidak dikenali: ' . implode(', ', $invalidHeaders);
                    $suggestions[] = '💡 Gunakan salah satu format header yang disediakan (case-insensitive)';
                    $suggestions[] = '📋 Download template Excel untuk melihat format yang benar';
                }
                
                if (!empty($duplicatePids)) {
                    $mainError = 'Semua data gagal diimport karena PID duplikat.';
                    $dupPids = array_slice(array_column($duplicatePids, 'pid'), 0, 5);
                    $suggestions[] = '🔴 PID duplikat ditemukan: ' . implode(', ', $dupPids) . (count($duplicatePids) > 5 ? '...' : '');
                    $suggestions[] = '💡 PID harus unik. Hapus atau ubah PID yang duplikat di file Excel';
                    $suggestions[] = '📊 Total ' . count($duplicatePids) . ' baris gagal karena duplikat';
                }
                
                if (!empty($detailedErrors)) {
                    $errorRows = array_slice(array_column($detailedErrors, 'row'), 0, 5);
                    $suggestions[] = '⚠️ Error validasi pada baris: ' . implode(', ', $errorRows) . (count($detailedErrors) > 5 ? '...' : '');
                    $suggestions[] = '💡 Periksa kolom wajib: Nama Proyek, Nama Mitra, PID, Phase';
                    $suggestions[] = '🔍 Detail error tersedia di response validation_details';
                }
                
                if ($import->getRowCount() === 0) {
                    $mainError = 'File Excel tidak memiliki data atau format header salah.';
                    $suggestions[] = '📝 Pastikan file Excel memiliki baris header di baris pertama';
                    $suggestions[] = '📋 Download template Excel untuk melihat format yang benar';
                    $suggestions[] = '✅ Kolom wajib: Nama Proyek, Nama Mitra, PID, Phase';
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $mainError,
                    'suggestions' => $suggestions,
                    'success_count' => 0,
                    'failed_count' => $import->getRowCount(),
                    'validation_details' => $errorDetails
                ], 400);
            }
            
            // [📤 EXCEL_OPERATIONS] PARTIAL SUCCESS WITH WARNINGS
            if (!empty($duplicatePids) || !empty($detailedErrors)) {
                $warnings = [];
                if (!empty($duplicatePids)) {
                    $warnings[] = count($duplicatePids) . ' data dilewati karena PID duplikat';
                }
                if (!empty($detailedErrors)) {
                    $warnings[] = count($detailedErrors) . ' data gagal karena error validasi';
                }
                
                return response()->json([
                    'success' => true,
                    'message' => "Import berhasil: $importedCount data ditambahkan",
                    'warnings' => $warnings,
                    'success_count' => $importedCount,
                    'failed_count' => count($duplicatePids) + count($detailedErrors),
                    'duplicate_pids' => $duplicatePids,
                    'detailed_errors' => $detailedErrors
                ], 200);
            }

            // Log import activity
            $this->logActivity(
                $request,
                'Import Excel',
                'import',
                "Mengimport $importedCount data proyek dari file Excel",
                'penagihan',
                null,
                null,
                ['total_imported' => $importedCount, 'before_count' => $beforeCount, 'after_count' => $afterCount]
            );
            
            return response()->json([
                'success' => true,
                'message' => "Import berhasil: $importedCount data ditambahkan",
                'success_count' => $importedCount,
                'failed_count' => 0
            ]);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            $detailedErrors = [];
            
            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values()
                ];

                $values = $failure->values();
                $attr = $failure->attribute();
                $detailLines = [];
                foreach ($failure->errors() as $msg) {
                    $detailLines[] = ($attr ? "{$attr}: " : '') . $msg;
                }

                $detailedErrors[] = [
                    'row' => $failure->row(),
                    'error' => 'Validasi kolom',
                    'details' => $detailLines,
                    'data_preview' => [
                        'pid' => $values['pid'] ?? '',
                        'nama_proyek' => $values['nama_proyek'] ?? '',
                        'nama_mitra' => $values['nama_mitra'] ?? '',
                        'phase' => $values['phase'] ?? '',
                    ],
                ];
            }

            $errorDetails = [
                'total_rows_processed' => count($failures),
                'detailed_errors' => $detailedErrors,
                'expected_headers' => \App\Imports\InvoicesImport::getExpectedHeaders(),
                'has_valid_data' => false,
            ];

            return response()->json([
                'success' => false,
                'message' => 'Terdapat error validasi di file Excel',
                'suggestions' => [
                    'Periksa kolom wajib: Nama Proyek, Nama Mitra, PID, Phase',
                    'Pastikan format angka Rekon Nilai hanya berisi angka',
                    'Gunakan template agar header sesuai'
                ],
                'failed_count' => count($failures),
                'success_count' => 0,
                'detailed_errors' => $detailedErrors,
                'validation_details' => $errorDetails,
                'errors' => $errors
            ], 422);

        } catch (\Exception $e) {
            Log::error('Import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * [📊 PROJECT_MANAGEMENT] [📤 EXCEL_OPERATIONS] Export data penagihan ke file Excel
     * Download sebagai blob dengan format spreadsheet yang rapi
     */
    public function export(Request $request)
    {
        $filters = $request->only(['status', 'search']);
        
        // Log export activity
        $this->logActivity(
            $request,
            'Export Excel',
            'export',
            'Mengexport data proyek ke file Excel',
            'penagihan',
            null,
            null,
            ['filters' => $filters]
        );
        
        return Excel::download(
            new InvoicesExport($filters), 
            'invoices_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * [📊 PROJECT_MANAGEMENT] [📤 EXCEL_OPERATIONS] Download template Excel untuk import
     * Template kosong dengan header dan format yang sudah disiapkan
     */
    public function downloadTemplate(Request $request)
    {
        // Log download template activity
        $this->logActivity(
            $request,
            'Download Template',
            'download',
            'Mendownload template Excel untuk import data proyek',
            'penagihan',
            null,
            null,
            null
        );
        
        return Excel::download(
            new InvoicesTemplateExport(), 
            'invoice_template.xlsx'
        );
    }

    /**
     * [📄 PROJECT_MANAGEMENT] [🗑️ BULK_DELETE] Hapus semua data proyek
     * Dengan konfirmasi dan logging untuk audit trail
     */
    public function destroyAll(Request $request): JsonResponse
    {
        // Validasi konfirmasi password atau token untuk keamanan
        $validator = Validator::make($request->all(), [
            'confirmation' => 'required|string|in:DELETE_ALL_PROJECTS',
            'exclude_prioritized' => 'sometimes|boolean', // Optional: skip data prioritas
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Konfirmasi tidak valid. Ketik "DELETE_ALL_PROJECTS" untuk konfirmasi.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $excludePrioritized = $request->input('exclude_prioritized', false);
            
            // Build query
            $query = Penagihan::query();
            
            // Exclude prioritized if requested
            if ($excludePrioritized) {
                $query->where(function($q) {
                    $q->whereNull('prioritas')
                      ->orWhere('prioritas', 0);
                });
            }
            
            // Get count before delete for logging
            $totalCount = $query->count();
            
            if ($totalCount === 0) {
                $message = $excludePrioritized 
                    ? 'Tidak ada data non-prioritas untuk dihapus' 
                    : 'Tidak ada data proyek untuk dihapus';
                    
                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 404);
            }

            // Get sample data for audit (first 5 records)
            $sampleData = $query->take(5)->get()->map(function ($item) {
                return [
                    'pid' => $item->pid,
                    'nama_proyek' => $item->nama_proyek,
                    'nama_mitra' => $item->nama_mitra,
                    'prioritas' => $item->prioritas
                ];
            })->toArray();

            // Count prioritized data that will be kept
            $keptCount = 0;
            if ($excludePrioritized) {
                $keptCount = Penagihan::whereNotNull('prioritas')
                    ->where('prioritas', '>', 0)
                    ->count();
            }

            // Delete records
            $query->delete();

            $deleteMessage = $excludePrioritized 
                ? "Menghapus {$totalCount} data proyek (mengecualikan {$keptCount} data prioritas)"
                : "Menghapus SEMUA data proyek ({$totalCount} records)";

            // Log activity
            $this->logActivity(
                $request,
                'Hapus Semua Proyek',
                'bulk_delete',
                $deleteMessage,
                'penagihan',
                null,
                [
                    'total_deleted' => $totalCount, 
                    'exclude_prioritized' => $excludePrioritized,
                    'kept_count' => $keptCount,
                    'sample_data' => $sampleData
                ],
                null
            );

            Log::warning("BULK DELETE: User {$request->user()->name} deleted {$totalCount} project records", [
                'user_id' => $request->user()->id,
                'total_deleted' => $totalCount,
                'exclude_prioritized' => $excludePrioritized,
                'kept_count' => $keptCount,
                'sample_data' => $sampleData
            ]);

            $responseMessage = $excludePrioritized && $keptCount > 0
                ? "Berhasil menghapus {$totalCount} data proyek. {$keptCount} data prioritas tidak dihapus."
                : "Berhasil menghapus {$totalCount} data proyek";

            return response()->json([
                'success' => true,
                'message' => $responseMessage,
                'total_deleted' => $totalCount,
                'kept_count' => $keptCount
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk delete error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * [📄 PROJECT_MANAGEMENT] [🗑️ BULK_DELETE] Hapus data proyek terpilih (selected)
     * Menghapus multiple data berdasarkan array PID
     */
    public function destroySelected(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pids' => 'required|array|min:1',
            'pids.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $pids = $request->input('pids');
            
            // Get data before delete for logging
            $dataToDelete = Penagihan::whereIn('pid', $pids)->get();
            
            if ($dataToDelete->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data yang ditemukan'
                ], 404);
            }

            $totalCount = $dataToDelete->count();
            
            // Create sample for audit
            $sampleData = $dataToDelete->take(5)->map(function ($item) {
                return [
                    'pid' => $item->pid,
                    'nama_proyek' => $item->nama_proyek,
                    'nama_mitra' => $item->nama_mitra
                ];
            })->toArray();

            // Delete records
            Penagihan::whereIn('pid', $pids)->delete();

            // Log activity
            $this->logActivity(
                $request,
                'Hapus Proyek Terpilih',
                'bulk_delete_selected',
                "Menghapus {$totalCount} data proyek terpilih",
                'penagihan',
                null,
                [
                    'total_deleted' => $totalCount,
                    'pids' => $pids,
                    'sample_data' => $sampleData
                ],
                null
            );

            Log::info("BULK DELETE SELECTED: User {$request->user()->name} deleted {$totalCount} selected records", [
                'user_id' => $request->user()->id,
                'total_deleted' => $totalCount,
                'pids' => $pids
            ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$totalCount} data proyek",
                'total_deleted' => $totalCount
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk delete selected error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
