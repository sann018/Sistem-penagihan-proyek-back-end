<?php

namespace App\Exports;

use App\Models\Penagihan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoicesExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Query data for export
     */
    public function query()
    {
        $query = Penagihan::query();

        if (isset($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (isset($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('nama_proyek', 'like', "%{$search}%")
                  ->orWhere('nama_mitra', 'like', "%{$search}%")
                  ->orWhere('pid', 'like', "%{$search}%")
                  ->orWhere('nomor_po', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Define headings
     */
    public function headings(): array
    {
        return [
            'ID',
            'NAMA_PROYEK',
            'NAMA_MITRA',
            'PID',
            'NOMOR_PO',
            'PHASE',
            'STATUS_CT',
            'STATUS_UT',
            'REKON_NILAI',
            'REKON_MATERIAL',
            'PELURUSAN_MATERIAL',
            'STATUS_PROCUREMENT',
            'TANGGAL_INVOICE',
            'TANGGAL_JATUH_TEMPO',
            'CATATAN',
            'TANGGAL_DIBUAT',
        ];
    }

    /**
     * Map data to columns
     */
    public function map($invoice): array
    {
        return [
            $invoice->id,
            $invoice->nama_proyek,
            $invoice->nama_mitra,
            $invoice->pid,
            $invoice->nomor_po,
            $invoice->phase,
            $invoice->status_ct,
            $invoice->status_ut,
            $invoice->rekon_nilai,
            $invoice->rekon_material,
            $invoice->pelurusan_material,
            $invoice->status_procurement,
            $invoice->tanggal_invoice ? $invoice->tanggal_invoice : '',
            $invoice->tanggal_jatuh_tempo ? $invoice->tanggal_jatuh_tempo : '',
            $invoice->catatan ?? '',
            $invoice->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Apply styles to worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'font' => [
                    'color' => ['rgb' => 'FFFFFF'],
                    'bold' => true
                ]
            ],
        ];
    }
}
