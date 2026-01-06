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
                'Pembangunan Site Melawi',      // nama_proyek
                'Mitra Borneo Jaya',            // nama_mitra
                'PID-2025-001',                 // pid
                'Po Material',                  // jenis_po
                '5500012345',                   // nomor_po
                'Phase 1',                      // phase
                'Belum CT',                     // status_ct
                'Belum UT',                     // status_ut
                'Sudah Rekap',                  // rekap_boq
                '3000000',                      // rekon_nilai
                'Sudah Rekon',                  // rekon_material
                'Sudah Lurus',                  // pelurusan_material
                'Proses Periv',                 // status_procurement
            ],
            [
                'Perbaikan Fiber Kapuas',       // nama_proyek
                'Cahaya Teleponindo',           // nama_mitra
                'PID-2026-002',                 // pid
                'Po Jasa',                      // jenis_po
                '5500012346',                   // nomor_po
                'Phase 2',                      // phase
                'Belum CT',                     // status_ct
                'Belum UT',                     // status_ut
                'Belum Rekap',                  // rekap_boq
                '2500000',                      // rekon_nilai
                'Belum Rekon',                  // rekon_material
                'Belum Lurus',                  // pelurusan_material
                'Antri Periv',                  // status_procurement
            ],
        ]);
    }

    /**
     * Return column headings (urutan sesuai dengan tabel)
     * PENTING: Header harus lowercase dan sesuai dengan field di InvoicesImport
     */
    public function headings(): array
    {
        return [
            'nama_proyek',
            'nama_mitra',
            'pid',
            'jenis_po',
            'nomor_po',
            'phase',
            'status_ct',
            'status_ut',
            'rekap_boq',
            'rekon_nilai',
            'rekon_material',
            'pelurusan_material',
            'status_procurement',
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
