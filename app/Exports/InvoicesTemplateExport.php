<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class InvoicesTemplateExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    /**
     * Return sample data for template
     */
    public function collection()
    {
        return collect([
            [
                'Proyek A',
                'Mitra Alpha',
                'PID001',
                'PO-1001',
                'Planning',
                'BELUM CT',
                'BELUM UT',
                '85000000',
                'BELUM REKON',
                'BELUM LURUS',
                'ANTRI PERIV',
                '2024-01-15',
                '2024-02-15',
                'Catatan proyek A',
            ],
            [
                'Proyek B',
                'Mitra Beta',
                'PID002',
                'PO-1002',
                'Execution',
                'SUDAH CT',
                'SUDAH UT',
                '100000000',
                'SUDAH REKON',
                'SUDAH LURUS',
                'OTW REG',
                '2024-01-20',
                '2024-03-20',
                'Catatan proyek B',
            ],
        ]);
    }

    /**
     * Return column headings (sesuai dengan mapping di InvoicesImport)
     */
    public function headings(): array
    {
        return [
            'nama_proyek',
            'nama_mitra',
            'pid',
            'nomor_po',
            'phase',
            'status_ct',
            'status_ut',
            'rekon_nilai',
            'rekon_material',
            'pelurusan_material',
            'status_procurement',
            'tanggal_invoice',
            'tanggal_jatuh_tempo',
            'catatan',
        ];
    }

    /**
     * Style the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DC2626'], // Red-600
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
