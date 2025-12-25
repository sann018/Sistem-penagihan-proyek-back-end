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

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $this->rowCount++;
        
        // Skip jika nama_proyek kosong (header row atau baris kosong)
        if (empty($row['nama_proyek'])) {
            Log::warning("Row {$this->rowCount} skipped: nama_proyek kosong");
            return null;
        }

        try {
            $penagihan = new Penagihan([
                'nama_proyek'         => trim($row['nama_proyek'] ?? ''),
                'nama_mitra'          => trim($row['nama_mitra'] ?? ''),
                'pid'                 => trim($row['pid'] ?? ''),
                'jenis_po'            => trim($row['jenis_po'] ?? ''),
                'nomor_po'            => trim($row['nomor_po'] ?? ''),
                'phase'               => trim($row['phase'] ?? ''),
                'status_ct'           => trim($row['status_ct'] ?? ''),
                'status_ut'           => trim($row['status_ut'] ?? ''),
                'rekap_boq'           => trim($row['rekap_boq'] ?? ''),
                'rekon_nilai'         => $this->parseNumber($row['rekon_nilai'] ?? 0),
                'rekon_material'      => trim($row['rekon_material'] ?? ''),
                'pelurusan_material'  => trim($row['pelurusan_material'] ?? ''),
                'status_procurement'  => trim($row['status_procurement'] ?? ''),
                // Auto-set timer: 30 hari dari sekarang
                'estimasi_durasi_hari' => 30,
                'tanggal_mulai'        => now()->toDateString(),
            ]);
            
            $this->successCount++;
            Log::info("Row {$this->rowCount} berhasil diproses: {$penagihan->nama_proyek}");
            return $penagihan;
        } catch (\Exception $e) {
            Log::error("Row {$this->rowCount} error: {$e->getMessage()}", ['row_data' => $row]);
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
            'nomor_po' => 'required|string|max:255',
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
}
