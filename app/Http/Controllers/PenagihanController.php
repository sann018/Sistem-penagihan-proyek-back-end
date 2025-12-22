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

class PenagihanController extends Controller
{
    /**
     * Display a listing of penagihan.
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

        return response()->json([
            'success' => true,
            'data' => $penagihan
        ]);
    }

    /**
     * Store a newly created penagihan.
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

        $penagihan = Penagihan::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Penagihan berhasil dibuat',
            'data' => $penagihan
        ], 201);
    }

    /**
     * Display the specified penagihan.
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
            'data' => $penagihan
        ]);
    }

    /**
     * Update the specified penagihan.
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

        $penagihan->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Penagihan berhasil diupdate',
            'data' => $penagihan
        ]);
    }

    /**
     * Remove the specified penagihan.
     */
    public function destroy(string $id): JsonResponse
    {
        $penagihan = Penagihan::find($id);

        if (!$penagihan) {
            return response()->json([
                'success' => false,
                'message' => 'Penagihan tidak ditemukan'
            ], 404);
        }

        $penagihan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Penagihan berhasil dihapus'
        ]);
    }

    /**
     * Get statistics dashboard.
     */
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
     * Import invoices from Excel file.
     * 
     * @param Request $request
     * @return JsonResponse
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
            /** @var InvoicesImport $import */
            $import = new InvoicesImport();
            
            // Import the Excel file
            Excel::import($import, $request->file('file'));

            // Get validation failures
            $failures = $import->failures(); // ✅ FIXED: Changed from onFailure() to failures()
            
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

                $totalRows = Penagihan::count();
                $failureCount = count($failures);

                return response()->json([
                    'success' => true,
                    'message' => "Import selesai: {$totalRows} berhasil, {$failureCount} gagal",
                    'success_count' => $totalRows,
                    'failed_count' => $failureCount,
                    'errors' => $errors
                ], 200);
            }

            // All rows imported successfully
            $totalRows = Penagihan::count();
            
            return response()->json([
                'success' => true,
                'message' => "Import berhasil: {$totalRows} data ditambahkan",
                'success_count' => $totalRows,
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
     * Export invoices to Excel file.
     */
    public function export(Request $request)
    {
        $filters = $request->only(['status', 'search']);
        
        return Excel::download(
            new InvoicesExport($filters), 
            'invoices_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * Download Excel template for import.
     */
    public function downloadTemplate()
    {
        return Excel::download(
            new InvoicesTemplateExport(), 
            'invoice_template.xlsx'
        );
    }
}
