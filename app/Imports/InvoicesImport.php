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

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Penagihan([
            'nama_proyek'         => $row['nama_proyek'] ?? null,
            'nama_mitra'          => $row['nama_mitra'] ?? null,
            'pid'                 => $row['pid'] ?? null,
            'nomor_po'            => $row['nomor_po'] ?? null,
            'phase'               => $row['phase'] ?? null,
            'status_ct'           => $row['status_ct'] ?? 'BELUM CT',
            'status_ut'           => $row['status_ut'] ?? 'BELUM UT',
            'rekon_nilai'         => $this->parseNumber($row['rekon_nilai'] ?? 0),
            'rekon_material'      => $row['rekon_material'] ?? 'BELUM REKON',
            'pelurusan_material'  => $row['pelurusan_material'] ?? 'BELUM LURUS',
            'status_procurement'  => $row['status_procurement'] ?? 'ANTRI PERIV',
            'tanggal_invoice'     => $this->parseDate($row['tanggal_invoice'] ?? null),
            'tanggal_jatuh_tempo' => $this->parseDate($row['tanggal_jatuh_tempo'] ?? null),
            'catatan'             => $row['catatan'] ?? null,
        ]);
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
            'pid' => 'required|string|unique:penagihan,pid',
            'nomor_po' => 'required|string|max:255',
            'phase' => 'required|string|max:255',
            'rekon_nilai' => 'required|numeric|min:0',
            'status_ct' => 'nullable|string|max:255',
            'status_ut' => 'nullable|string|max:255',
            'rekon_material' => 'nullable|string|max:255',
            'pelurusan_material' => 'nullable|string|max:255',
            'status_procurement' => 'nullable|string|max:255',
            'tanggal_invoice' => 'nullable|date',
            'tanggal_jatuh_tempo' => 'nullable|date',
            'catatan' => 'nullable|string',
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
}
