<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Import');

// Define headers (13 kolom)
$headers = [
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
    'status_procurement'
];

// Set headers with styling
$headerRow = 1;
foreach ($headers as $col => $header) {
    $cell = $sheet->getCellByColumnAndRow($col + 1, $headerRow);
    $cell->setValue($header);
    
    // Style header
    $cell->getStyle()
        ->getFont()
        ->setBold(true)
        ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
    
    $cell->getStyle()
        ->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()
        ->setRGB('DC2626');
}

// Generate random data (20 rows)
$proyek_list = [
    'Pembangunan Site Melawi',
    'Perbaikan Fiber Kapuas',
    'Optimalisasi Node Landak',
    'Upgrade Tower Pontianak',
    'Instalasi Fiber Mempawah',
    'Maintenance Network Sambas',
    'Ekspansi Coverage Sanggau',
    'Refurbishment BTS Sekadau',
    'Deployment Server Regional',
    'Integrasi Sistem Billing',
];

$mitra_list = [
    'Mitra Borneo Jaya',
    'Cahaya Teleponindo',
    'Sinergi Utama',
    'Digital Solutions Ltd',
    'Tech Corp Inc',
    'Future Tech Co',
    'Global Systems',
    'Creative Agency',
    'Network Services Pro',
    'Cloud Infrastructure Plus',
];

$jenis_po_list = [
    'Po Material',
    'Po Jasa',
    'Po Full',
    'Po Partial',
    'Po Maintenance',
];

$phase_list = [
    'Phase 1',
    'Phase 2',
    'Phase 3',
    'Phase 4',
    'Phase 5',
    'Konstruksi',
    'Testing',
    'Deployment',
];

$status_ct_list = ['BELUM CT', 'SUDAH CT'];
$status_ut_list = ['BELUM UT', 'SUDAH UT'];
$rekap_boq_list = ['Belum Rekap', 'Sudah Rekap'];
$rekon_material_list = ['Belum Rekon', 'Sudah Rekon'];
$pelurusan_material_list = ['Belum Lurus', 'Sudah Lurus'];
$status_procurement_list = ['Antri Periv', 'Proses Periv', 'Sekuler Ttd', 'OTW REG'];

// Insert data rows
for ($row = 2; $row <= 21; $row++) {
    $rowIndex = $row - 1;
    
    $data = [
        $proyek_list[array_rand($proyek_list)] . ' ' . $rowIndex,
        $mitra_list[array_rand($mitra_list)],
        'PID-' . date('Y') . '-' . str_pad($rowIndex, 3, '0', STR_PAD_LEFT),
        $jenis_po_list[array_rand($jenis_po_list)],
        'PO-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT) . '-' . date('Y'),
        $phase_list[array_rand($phase_list)],
        $status_ct_list[array_rand($status_ct_list)],
        $status_ut_list[array_rand($status_ut_list)],
        $rekap_boq_list[array_rand($rekap_boq_list)],
        random_int(1000000, 50000000), // rekon_nilai
        $rekon_material_list[array_rand($rekon_material_list)],
        $pelurusan_material_list[array_rand($pelurusan_material_list)],
        $status_procurement_list[array_rand($status_procurement_list)],
    ];
    
    foreach ($data as $col => $value) {
        $sheet->getCellByColumnAndRow($col + 1, $row)->setValue($value);
    }
}

// Auto size columns
foreach (range('A', 'M') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Save file
$filename = 'TEST_IMPORT_' . date('Y-m-d_H-i-s') . '.xlsx';
$filepath = __DIR__ . '/storage/app/' . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

echo "✅ File Excel test berhasil dibuat!\n";
echo "📄 Nama file: $filename\n";
echo "📍 Lokasi: storage/app/$filename\n";
echo "📊 Total baris data: 20\n";
echo "📋 Total kolom: 13\n";
echo "\n🔗 Download dari aplikasi atau copy dari storage/app/$filename\n";
?>
