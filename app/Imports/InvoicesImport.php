<?php

namespace App\Imports;

use App\Models\Penagihan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\Log;

class InvoicesImport implements 
    ToModel, 
    WithHeadingRow,
    WithValidation,
    SkipsOnError,
    SkipsOnFailure,
    WithBatchInserts,
    WithChunkReading
{
    private array $errors = [];
    private array $failures = [];
    private int $successCount = 0;
    private int $rowCount = 0;
    private array $duplicatePids = [];
    private array $missingHeaders = [];
    private array $detailedErrors = [];
    private bool $hasValidData = false;
    private array $headerMapping = [];
    private array $invalidHeaders = [];

    /**
     * Get header mapping untuk flexible column names
     * Mapping antara header user-friendly dengan nama kolom database
     */
    private function getHeaderMapping(): array
    {
        return [
            // Header user-friendly => nama kolom database
            'nama_proyek' => 'nama_proyek',
            'nama proyek' => 'nama_proyek',
            'proyek' => 'nama_proyek',
            'project name' => 'nama_proyek',
            
            'nama_mitra' => 'nama_mitra',
            'nama mitra' => 'nama_mitra',
            'mitra' => 'nama_mitra',
            'partner' => 'nama_mitra',
            
            'pid' => 'pid',
            'project id' => 'pid',
            'id proyek' => 'pid',
            
            'jenis_po' => 'jenis_po',
            'jenis po' => 'jenis_po',
            'tipe po' => 'jenis_po',
            'po type' => 'jenis_po',
            
            'nomor_po' => 'nomor_po',
            'nomor po' => 'nomor_po',
            'no po' => 'nomor_po',
            'po number' => 'nomor_po',
            
            'phase' => 'phase',
            'fase' => 'phase',
            'tahap' => 'phase',
            
            'status_ct' => 'status_ct',
            'status ct' => 'status_ct',
            
            'status_ut' => 'status_ut',
            'status ut' => 'status_ut',
            
            'rekap_boq' => 'rekap_boq',
            'rekap boq' => 'rekap_boq',
            
            'rekon_nilai' => 'rekon_nilai',
            'rekon nilai' => 'rekon_nilai',
            'nilai rekon' => 'rekon_nilai',
            'reconciliation value' => 'rekon_nilai',
            
            'rekon_material' => 'rekon_material',
            'rekon material' => 'rekon_material',
            
            'pelurusan_material' => 'pelurusan_material',
            'pelurusan material' => 'pelurusan_material',
            
            'status_procurement' => 'status_procurement',
            'status procurement' => 'status_procurement',
        ];
    }

    /**
     * Normalize header name untuk mapping
     */
    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(str_replace(['_', '-'], ' ', $header)));
    }

    /**
     * Map row data dengan header mapping yang flexible
     */
    private function mapRowData(array $row): array
    {
        $mapping = $this->getHeaderMapping();
        $mappedData = [];
        
        foreach ($row as $key => $value) {
            $normalizedKey = $this->normalizeHeader($key);
            
            // Cek apakah header ada di mapping
            if (isset($mapping[$normalizedKey])) {
                $dbColumn = $mapping[$normalizedKey];
                $mappedData[$dbColumn] = $value;
            } else {
                // Track invalid headers
                if (!in_array($key, $this->invalidHeaders)) {
                    $this->invalidHeaders[] = $key;
                }
            }
        }
        
        return $mappedData;
    }

    /**
     * Validasi apakah semua kolom wajib ada di row
     */
    private function validateRequiredColumns(array $row, int $rowNumber): array
    {
        $errors = [];
        $required = ['nama_proyek', 'nama_mitra', 'pid', 'phase'];
        
        foreach ($required as $column) {
            if (!isset($row[$column]) || trim($row[$column]) === '') {
                $errors[] = $this->getColumnDisplayName($column) . ' tidak boleh kosong';
            }
        }
        
        return $errors;
    }

    /**
     * Get display name untuk kolom (user-friendly)
     */
    private function getColumnDisplayName(string $column): string
    {
        $displayNames = [
            'nama_proyek' => 'Nama Proyek',
            'nama_mitra' => 'Nama Mitra',
            'pid' => 'PID',
            'jenis_po' => 'Jenis PO',
            'nomor_po' => 'Nomor PO',
            'phase' => 'Phase',
            'status_ct' => 'Status CT',
            'status_ut' => 'Status UT',
            'rekap_boq' => 'Rekap BOQ',
            'rekon_nilai' => 'Rekon Nilai',
            'rekon_material' => 'Rekon Material',
            'pelurusan_material' => 'Pelurusan Material',
            'status_procurement' => 'Status Procurement',
        ];
        
        return $displayNames[$column] ?? $column;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $this->rowCount++;
        
        // Map row data dengan flexible header mapping
        $mappedRow = $this->mapRowData($row);
        // Map row data dengan flexible header mapping
        $mappedRow = $this->mapRowData($row);
        
        // Skip jika nama_proyek kosong (header row atau baris kosong)
        if (empty($mappedRow['nama_proyek'])) {
            Log::warning("Row {$this->rowCount} skipped: nama_proyek kosong");
            return null;
        }

        try {
            // Validasi kolom wajib
            $validationErrors = $this->validateRequiredColumns($mappedRow, $this->rowCount);
            if (!empty($validationErrors)) {
                $this->detailedErrors[] = [
                    'row' => $this->rowCount + 1,
                    'error' => 'Validasi gagal',
                    'details' => $validationErrors,
                    'data_preview' => [
                        'nama_proyek' => $mappedRow['nama_proyek'] ?? '',
                        'pid' => $mappedRow['pid'] ?? '',
                    ]
                ];
                Log::warning("Row {$this->rowCount} skipped: Validasi gagal - " . implode(', ', $validationErrors));
                return null;
            }
            
            $pid = trim($mappedRow['pid'] ?? '');
            
            // Validasi format PID
            if (empty($pid)) {
                $this->detailedErrors[] = [
                    'row' => $this->rowCount + 1,
                    'error' => 'PID tidak boleh kosong',
                    'details' => ['PID adalah identifier unik untuk setiap proyek dan wajib diisi'],
                    'data_preview' => ['nama_proyek' => $mappedRow['nama_proyek'] ?? '']
                ];
                return null;
            }
            
            // Check PID duplikat di database
            $exists = Penagihan::where('pid', $pid)->exists();
            if ($exists) {
                $this->duplicatePids[] = [
                    'row' => $this->rowCount + 1,
                    'pid' => $pid,
                    'nama_proyek' => trim($mappedRow['nama_proyek'] ?? ''),
                    'reason' => 'PID ini sudah terdaftar di database'
                ];
                Log::warning("Row {$this->rowCount} skipped: PID duplikat - {$pid}");
                return null;
            }
            
            // Validasi format rekon_nilai (harus numerik)
            $rekonNilai = $this->parseNumber($mappedRow['rekon_nilai'] ?? 0);
            if (isset($mappedRow['rekon_nilai']) && !empty($mappedRow['rekon_nilai']) && !is_numeric($rekonNilai)) {
                $this->detailedErrors[] = [
                    'row' => $this->rowCount + 1,
                    'error' => 'Format Rekon Nilai tidak valid',
                    'details' => [
                        'Rekon Nilai harus berupa angka',
                        'Format yang diterima: 8500000 atau 8.500.000 atau 8,500,000'
                    ],
                    'data_preview' => [
                        'pid' => $pid,
                        'rekon_nilai' => $mappedRow['rekon_nilai'] ?? ''
                    ]
                ];
                return null;
            }
            
            $this->hasValidData = true;
            
            $penagihan = new Penagihan([
                'nama_proyek'         => trim($mappedRow['nama_proyek'] ?? ''),
                'nama_mitra'          => trim($mappedRow['nama_mitra'] ?? ''),
                'pid'                 => $pid,
                'jenis_po'            => trim($mappedRow['jenis_po'] ?? ''),
                'nomor_po'            => ($v = trim($mappedRow['nomor_po'] ?? '')) !== '' ? $v : null,
                'phase'               => trim($mappedRow['phase'] ?? ''),
                'status_ct'           => trim($mappedRow['status_ct'] ?? ''),
                'status_ut'           => trim($mappedRow['status_ut'] ?? ''),
                'rekap_boq'           => trim($mappedRow['rekap_boq'] ?? ''),
                'rekon_nilai'         => $rekonNilai,
                'rekon_material'      => trim($mappedRow['rekon_material'] ?? ''),
                'pelurusan_material'  => trim($mappedRow['pelurusan_material'] ?? ''),
                'status_procurement'  => trim($mappedRow['status_procurement'] ?? ''),
                // Auto-set timer: 30 hari dari sekarang
                'estimasi_durasi_hari' => 30,
                'tanggal_mulai'        => now()->toDateString(),
            ]);
            
            $this->successCount++;
            Log::info("Row {$this->rowCount} berhasil diproses: {$penagihan->nama_proyek}");
            return $penagihan;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $errorType = 'Error tidak diketahui';
            $suggestions = [];
            
            // Kategorikan error dan berikan saran
            if (str_contains($errorMessage, 'Duplicate entry')) {
                $errorType = 'Data duplikat';
                $suggestions = [
                    'PID atau data lain sudah ada di database',
                    'Periksa kembali data yang akan diimport',
                    'Hapus data duplikat dari file Excel'
                ];
            } elseif (str_contains($errorMessage, 'Data too long')) {
                $errorType = 'Data terlalu panjang';
                $suggestions = [
                    'Salah satu kolom melebihi batas maksimum karakter',
                    'Periksa panjang teks di kolom Nama Proyek, Nama Mitra, dll',
                    'Maksimum 255 karakter untuk sebagian besar kolom'
                ];
            } elseif (str_contains($errorMessage, 'Incorrect') || str_contains($errorMessage, 'Invalid')) {
                $errorType = 'Format data tidak valid';
                $suggestions = [
                    'Format data tidak sesuai dengan yang diharapkan',
                    'Periksa format tanggal, angka, atau tipe data lainnya'
                ];
            }
            
            Log::error("Row {$this->rowCount} error: {$errorMessage}", ['row_data' => $mappedRow]);
            $this->detailedErrors[] = [
                'row' => $this->rowCount + 1,
                'error' => $errorType,
                'message' => $errorMessage,
                'suggestions' => $suggestions,
                'data_preview' => [
                    'nama_proyek' => $mappedRow['nama_proyek'] ?? '',
                    'pid' => $mappedRow['pid'] ?? '',
                    'phase' => $mappedRow['phase'] ?? ''
                ]
            ];
            return null;
        }
    }

    /**
     * Parse number dari format Indonesia (8.500.000,00) atau biasa (8500000)
     */
    private function parseNumber($value)
    {
        if (empty($value)) return 0;

        // Remove dots (thousand separator) and replace comma with dot (decimal)
        $cleaned = str_replace(['.', ','], ['', '.'], (string)$value);
        
        return (float)$cleaned;
    }

    /**
     * Parse tanggal dari berbagai format
     */
    private function parseDate($value)
    {
        if (empty($value)) return null;

        try {
            // Jika sudah format Carbon/DateTime
            if ($value instanceof \DateTime) {
                return $value->format('Y-m-d');
            }

            // Parse string date
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'nama_proyek' => 'required|string|max:255',
            'nama_mitra' => 'required|string|max:255',
            'pid' => 'required|string',
            'jenis_po' => 'nullable|string|max:255',
            'nomor_po' => 'nullable|string|max:255',
            'phase' => 'required|string|max:255',
            'status_ct' => 'nullable|string|max:255',
            'status_ut' => 'nullable|string|max:255',
            'rekap_boq' => 'nullable|string|max:255',
            'rekon_nilai' => 'nullable|numeric|min:0',
            'rekon_material' => 'nullable|string|max:255',
            'pelurusan_material' => 'nullable|string|max:255',
            'status_procurement' => 'nullable|string|max:255',
        ];
    }
    
    /**
     * Custom validation messages
     */
    public function customValidationMessages(): array
    {
        return [
            'nama_proyek.required' => 'Nama Proyek wajib diisi',
            'nama_mitra.required' => 'Nama Mitra wajib diisi',
            'pid.required' => 'PID wajib diisi',
            'phase.required' => 'Phase wajib diisi',
            'rekon_nilai.numeric' => 'Rekon Nilai harus berupa angka',
        ];
    }

    /**
     * Handle errors during import
     * 
     * @param Throwable $error
     * @return void
     */
    public function onError(Throwable $error): void
    {
        $this->errors[] = $error->getMessage();
    }

    /**
     * Collect validation failures
     * Required by SkipsOnFailure interface
     *
     * @param Failure ...$failures
     * @return void
     */
    public function onFailure(Failure ...$failures): void
    {
        $this->failures = array_merge($this->failures, $failures);
    }

    /**
     * Get all validation failures
     * This method is called by PenagihanController
     *
     * @return Failure[]
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * Get all errors
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Get success count
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get total rows processed
     */
    public function getRowCount(): int
    {
        return $this->rowCount;
    }
    
    /**
     * Get duplicate PIDs
     */
    public function getDuplicatePids(): array
    {
        return $this->duplicatePids;
    }
    
    /**
     * Get detailed errors
     */
    public function getDetailedErrors(): array
    {
        return $this->detailedErrors;
    }
    
    /**
     * Check if has valid data
     */
    public function hasValidData(): bool
    {
        return $this->hasValidData;
    }
    
    /**
     * Get expected headers dengan format user-friendly
     */
    public static function getExpectedHeaders(): array
    {
        return [
            [
                'kolom' => 'Nama Proyek',
                'wajib' => true,
                'format' => 'Teks',
                'contoh' => 'Proyek Pembangunan Tower ABC',
                'alternatif' => ['nama_proyek', 'proyek', 'project name']
            ],
            [
                'kolom' => 'Nama Mitra',
                'wajib' => true,
                'format' => 'Teks',
                'contoh' => 'PT Mitra Sejahtera',
                'alternatif' => ['nama_mitra', 'mitra', 'partner']
            ],
            [
                'kolom' => 'PID',
                'wajib' => true,
                'format' => 'Teks unik',
                'contoh' => 'PID-2026-001',
                'alternatif' => ['pid', 'project id', 'id proyek'],
                'catatan' => 'Harus unik, tidak boleh duplikat'
            ],
            [
                'kolom' => 'Phase',
                'wajib' => true,
                'format' => 'Teks',
                'contoh' => 'Phase 1',
                'alternatif' => ['phase', 'fase', 'tahap']
            ],
            [
                'kolom' => 'Jenis PO',
                'wajib' => false,
                'format' => 'Teks',
                'contoh' => 'PO Reguler',
                'alternatif' => ['jenis_po', 'jenis po', 'tipe po']
            ],
            [
                'kolom' => 'Nomor PO',
                'wajib' => false,
                'format' => 'Teks',
                'contoh' => 'PO/2026/001',
                'alternatif' => ['nomor_po', 'nomor po', 'no po']
            ],
            [
                'kolom' => 'Status CT',
                'wajib' => false,
                'format' => 'Teks',
                'contoh' => 'Completed',
                'alternatif' => ['status_ct', 'status ct']
            ],
            [
                'kolom' => 'Status UT',
                'wajib' => false,
                'format' => 'Teks',
                'contoh' => 'In Progress',
                'alternatif' => ['status_ut', 'status ut']
            ],
            [
                'kolom' => 'Rekap BOQ',
                'wajib' => false,
                'format' => 'Teks',
                'contoh' => 'BOQ Final',
                'alternatif' => ['rekap_boq', 'rekap boq']
            ],
            [
                'kolom' => 'Rekon Nilai',
                'wajib' => false,
                'format' => 'Angka',
                'contoh' => '8500000 atau 8.500.000',
                'alternatif' => ['rekon_nilai', 'rekon nilai', 'nilai rekon'],
                'catatan' => 'Format: tanpa simbol mata uang, boleh pakai titik/koma sebagai pemisah'
            ],
            [
                'kolom' => 'Rekon Material',
                'wajib' => false,
                'format' => 'Teks',
                'contoh' => 'Material Approved',
                'alternatif' => ['rekon_material', 'rekon material']
            ],
            [
                'kolom' => 'Pelurusan Material',
                'wajib' => false,
                'format' => 'Teks',
                'contoh' => 'Done',
                'alternatif' => ['pelurusan_material', 'pelurusan material']
            ],
            [
                'kolom' => 'Status Procurement',
                'wajib' => false,
                'format' => 'Teks',
                'contoh' => 'Completed',
                'alternatif' => ['status_procurement', 'status procurement']
            ],
        ];
    }
    
    /**
     * Get invalid headers yang ditemukan
     */
    public function getInvalidHeaders(): array
    {
        return array_unique($this->invalidHeaders);
    }
}
