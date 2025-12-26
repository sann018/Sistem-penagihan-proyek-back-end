<?php

namespace App\Http\Controllers;

use App\Models\Penagihan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
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
        if ($request->get('dashboard') === 'true' || $request->get('dashboard') === true) {
            $query->prioritized(); // scope dari Model
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

        // Sorting (kecuali untuk dashboard yang sudah ada custom sorting di scope)
        if (!$request->get('dashboard')) {
            $sortBy = $request->get('sort_by', 'dibuat_pada');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
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
        if ($prioritas === 1) {
            return 'Prioritas Tinggi';
        }
        if ($prioritas === 2) {
            return 'Mendekati Deadline';
        }
        return null;
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

        return $penagihan;
    }

    /**
     * [📊 PROJECT_MANAGEMENT] Buat project penagihan baru
     * Validasi semua field yang diperlukan
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nama_proyek' => 'required|string|max:255',
            'nama_mitra' => 'required|string|max:255',
            'pid' => 'required|string|unique:penagihan,pid',
            'jenis_po' => 'nullable|string|max:255',
            'nomor_po' => 'required|string|max:255',
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
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prepare data
        $data = $request->all();
        
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
            $penagihan->id,
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

        $validator = Validator::make($request->all(), [
            'nama_proyek' => 'sometimes|required|string|max:255',
            'nama_mitra' => 'sometimes|required|string|max:255',
            'pid' => 'sometimes|required|string|unique:penagihan,pid,' . $id,
            'jenis_po' => 'nullable|string|max:255',
            'nomor_po' => 'sometimes|required|string|max:255',
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
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
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
            $penagihan->id,
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
     * [🎯 PRIORITY_SYSTEM] Set/Update prioritas manual untuk proyek
     * prioritas: 1 = Prioritas Tinggi (manual), null = hapus prioritas
     */
    public function setPrioritize(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prioritas' => 'nullable|integer|in:1,2', // 1=P1 (urgent), 2=P2 (important), null=hapus
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
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

        $penagihan->update([
            'prioritas' => $newPrioritas,
            'prioritas_updated_at' => now()
        ]);

        // Log activity
        $priorityLabel = $newPrioritas === 1 ? 'P1 (Urgent)' : ($newPrioritas === 2 ? 'P2 (Important)' : 'Hapus');
        $action = $newPrioritas ? "Set Prioritas {$priorityLabel}" : 'Hapus Prioritas';
        $this->logActivity(
            $request,
            $action,
            'update',
            "Mengubah prioritas proyek '{$penagihan->nama_proyek}' dari {$oldPrioritas} ke {$newPrioritas}",
            'penagihan',
            $penagihan->id,
            ['prioritas' => $oldPrioritas],
            ['prioritas' => $newPrioritas]
        );

        $successMessage = $newPrioritas === 1 ? 'Proyek berhasil di-set sebagai Prioritas 1 (Urgent)' : 
                         ($newPrioritas === 2 ? 'Proyek berhasil di-set sebagai Prioritas 2 (Important)' : 'Prioritas berhasil dihapus');

        return response()->json([
            'success' => true,
            'message' => $successMessage,
            'data' => $this->addTimerInfo($penagihan->fresh())
        ]);
    }

    /**
     * [🎯 PRIORITY_SYSTEM] Auto-prioritize proyek yang mendekati deadline
     * Otomatis set prioritas = 2 untuk proyek dengan sisa waktu <= 7 hari
     * Yang sudah prioritas manual (1) tidak akan diubah
     */
    public function autoPrioritize(): JsonResponse
    {
        try {
            $threshold = 3; // hari (H-3 menuju deadline)
            $updated = 0;
            $cleared = 0;
            $skipped = 0;
            $debugInfo = [];

            // Get semua proyek yang punya timer
            $projects = Penagihan::whereNotNull('tanggal_mulai')
                ->whereNotNull('estimasi_durasi_hari')
                ->get();

            Log::info("Auto-prioritize started. Total projects with timers: " . $projects->count());

            foreach ($projects as $project) {
                $projectInfo = [
                    'id' => $project->id,
                    'nama' => $project->nama_proyek,
                    'prioritas_awal' => $project->prioritas
                ];

                // Skip yang sudah prioritas manual (P1)
                if ($project->prioritas === 1) {
                    $projectInfo['action'] = 'skipped_p1_manual';
                    $debugInfo[] = $projectInfo;
                    $skipped++;
                    continue;
                }

                $daysRemaining = $project->getDaysUntilDeadline();

                if ($daysRemaining === null) {
                    $projectInfo['action'] = 'skipped_no_days';
                    $debugInfo[] = $projectInfo;
                    $skipped++;
                    continue;
                }

                $projectInfo['days_remaining'] = $daysRemaining;
                $projectInfo['is_completed'] = $project->isCompleted();

                // Set prioritas 2 jika:
                // 1. Mendekati deadline (0 sampai 3 hari) ATAU
                // 2. OVERDUE (negatif hari) <- LEBIH URGENT!
                // DAN belum selesai
                if ($daysRemaining <= $threshold && !$project->isCompleted()) {
                    if ($project->prioritas !== 2) {
                        $project->update([
                            'prioritas' => 2,
                            'prioritas_updated_at' => now()
                        ]);
                        $projectInfo['action'] = 'set_p2';
                        $projectInfo['reason'] = $daysRemaining < 0 ? 'overdue' : 'approaching_deadline';
                        $debugInfo[] = $projectInfo;
                        $updated++;
                        Log::info("Set P2 for project {$project->id}: {$project->nama_proyek} (days: {$daysRemaining})");
                    } else {
                        $projectInfo['action'] = 'already_p2';
                        $debugInfo[] = $projectInfo;
                    }
                }
                // Clear prioritas 2 jika sudah lewat threshold (ke atas) atau sudah selesai
                elseif ($project->prioritas === 2) {
                    $project->update([
                        'prioritas' => null,
                        'prioritas_updated_at' => now()
                    ]);
                    $projectInfo['action'] = 'cleared_p2';
                    $projectInfo['reason'] = $daysRemaining > $threshold ? 'over_threshold' : 'completed';
                    $debugInfo[] = $projectInfo;
                    $cleared++;
                    Log::info("Clear P2 for project {$project->id}: {$project->nama_proyek} (days: {$daysRemaining}, completed: " . ($project->isCompleted() ? 'yes' : 'no') . ")");
                } else {
                    $projectInfo['action'] = 'no_change';
                    $debugInfo[] = $projectInfo;
                }
            }

            Log::info("Auto-prioritize completed. Updated: {$updated}, Cleared: {$cleared}, Skipped: {$skipped}");

            return response()->json([
                'success' => true,
                'message' => 'Auto-prioritize berhasil dijalankan',
                'data' => [
                    'updated' => $updated,
                    'cleared' => $cleared,
                    'skipped' => $skipped,
                    'threshold_days' => $threshold,
                    'debug' => $debugInfo // Untuk debugging
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Auto-prioritize error: ' . $e->getMessage());
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
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
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
                
                /** @var \Maatwebsite\Excel\Validators\Failure $failure */
                foreach ($failures as $failure) {
                    $errors[] = [
                        'row' => $failure->row(),
                        'attribute' => $failure->attribute(),
                        'errors' => $failure->errors(),
                        'values' => $failure->values()
                    ];
                }

                // [📤 EXCEL_OPERATIONS] HITUNG FAILURE COUNT
                $failureCount = count($failures);
                
                Log::error("Import Excel validation failures: " . json_encode($errors));

                return response()->json([
                    'success' => true,
                    'message' => "Import selesai: $importedCount berhasil, $failureCount gagal",
                    'success_count' => $importedCount,
                    'failed_count' => $failureCount,
                    'validation_errors' => $errors
                ], 200);
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

            // [📤 EXCEL_OPERATIONS] SEMUA BARIS BERHASIL DIIMPORT
            if ($importedCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import gagal: Tidak ada data valid yang diimport. Pastikan file Excel memiliki header dan data dengan benar.',
                    'success_count' => 0,
                    'failed_count' => 0,
                    'debug_info' => [
                        'before_count' => $beforeCount,
                        'after_count' => $afterCount,
                        'row_processed' => $import->getRowCount(),
                        'rows_success' => $import->getSuccessCount()
                    ]
                ], 400);
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
            
            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values()
                ];
            }

            return response()->json([
                'success' => false,
                'message' => 'Terdapat error validasi di file Excel',
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
}
