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
     * Mendukung search, filter status, card filter, dan sorting
     */
    public function index(Request $request): JsonResponse
    {
        $query = Penagihan::query();

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
                    // Semua 6 status dropdown = selesai/hijau
                    $query->where('status_ct', 'Sudah CT')
                          ->where('status_ut', 'Sudah UT')
                          ->where('rekap_boq', 'Sudah Rekap')
                          ->where('rekon_material', 'Sudah Rekon')
                          ->where('pelurusan_material', 'Sudah Lurus')
                          ->where('status_procurement', 'OTW REG');
                    break;
                    
                case 'tertunda':
                    // Status Procurement = Revisi Mitra
                    $query->where('status_procurement', 'Revisi Mitra');
                    break;
                    
                case 'belum_rekon':
                    // Rekap BOQ = Belum Rekap
                    $query->where('rekap_boq', 'Belum Rekap');
                    break;
                    
                case 'sedang_berjalan':
                    // Ada salah satu status yang belum selesai (not sudah_penuh dan not tertunda)
                    $query->where(function($q) {
                        $q->where('status_ct', '!=', 'Sudah CT')
                          ->orWhere('status_ut', '!=', 'Sudah UT')
                          ->orWhere('rekap_boq', '!=', 'Sudah Rekap')
                          ->orWhere('rekon_material', '!=', 'Sudah Rekon')
                          ->orWhere('pelurusan_material', '!=', 'Sudah Lurus')
                          ->orWhere('status_procurement', '!=', 'OTW REG');
                    })
                    ->where('status_procurement', '!=', 'Revisi Mitra');
                    break;
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'dibuat_pada');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $penagihan = $query->paginate($perPage);

        // Add timer info untuk setiap project
        $penagihan->getCollection()->transform(function ($item) {
            return $this->addTimerInfo($item);
        });

        return response()->json([
            'success' => true,
            'data' => $penagihan
        ]);
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
     * - Sudah Penuh: Semua status dropdown berwarna hijau (selesai)
     * - Sedang Berjalan: Ada status dropdown yang belum selesai (merah/kuning)
     * - Tertunda: Status Procurement = "Revisi Mitra"
     * - Belum Rekon: Rekap BOQ = "Belum Rekap"
     */
    public function cardStatistics(): JsonResponse
    {
        // Define status yang dianggap "Selesai" (hijau)
        $completedStatus = [
            'status_ct' => 'Sudah CT',
            'status_ut' => 'Sudah UT',
            'rekap_boq' => 'Sudah Rekap',
            'rekon_material' => 'Sudah Rekon',
            'pelurusan_material' => 'Sudah Lurus',
            'status_procurement' => 'OTW REG' // atau status final lainnya
        ];

        // Count Sudah Penuh (semua status selesai/hijau)
        $sudahPenuh = Penagihan::where('status_ct', 'Sudah CT')
            ->where('status_ut', 'Sudah UT')
            ->where('rekap_boq', 'Sudah Rekap')
            ->where('rekon_material', 'Sudah Rekon')
            ->where('pelurusan_material', 'Sudah Lurus')
            ->where('status_procurement', 'OTW REG')
            ->count();

        // Count Tertunda (Status Procurement = Revisi Mitra)
        $tertunda = Penagihan::where('status_procurement', 'Revisi Mitra')->count();

        // Count Belum Rekon (Rekap BOQ = Belum Rekap)
        $belumRekon = Penagihan::where('rekap_boq', 'Belum Rekap')->count();

        // Count Sedang Berjalan (Ada salah satu status yang belum selesai, exclude yang Tertunda)
        $totalProyek = Penagihan::count();
        $sedangBerjalan = $totalProyek - $sudahPenuh - $tertunda;

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
