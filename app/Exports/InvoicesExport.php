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

        return $query->orderBy('dibuat_pada', 'desc');
    }

    /**
     * Define headings
     */
    public function headings(): array
    {
        return [
            'NAMA_PROYEK',
            'NAMA_MITRA',
            'PID',
            'JENIS_PO',
            'NOMOR_PO',
            'PHASE',
            'REKON_NILAI',
            'STATUS_CT',
            'STATUS_UT',
            'REKAP_BOQ',
            'REKON_MATERIAL',
            'PELURUSAN_MATERIAL',
            'STATUS_PROCUREMENT',
        ];
    }

    /**
     * Map data to columns
     */
    public function map($invoice): array
    {
        return [
            $invoice->nama_proyek,
            $invoice->nama_mitra,
            $invoice->pid,
            $invoice->jenis_po ?? '',
            $invoice->nomor_po,
            $invoice->phase,
            $invoice->rekon_nilai,
            $invoice->status_ct,
            $invoice->status_ut,
            $invoice->rekap_boq ?? '',
            $invoice->rekon_material ?? '',
            $invoice->pelurusan_material ?? '',
            $invoice->status_procurement ?? '',
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
