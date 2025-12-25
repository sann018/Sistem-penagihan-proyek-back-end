<?php
// Test import tanpa Laravel
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create test data matching user's data
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$headers = ['nama_proyek', 'nama_mitra', 'pid', 'jenis_po', 'nomor_po', 'phase', 'status_ct', 'status_ut', 'rekap_boq', 'rekon_nilai', 'rekon_material', 'pelurusan_material', 'status_procurement'];

foreach ($headers as $col => $header) {
    $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
}

// Set data rows (from user's data)
$data = [
    ['Pembangunan Site Melawi', 'Mitra Borneo Jaya', 'PID-2025-001', 'Po Material', '5500012345', 'Phase 1', 'SUDAH CT', 'SUDAH UT', 'Sudah Rekap', '3000000', 'Sudah Rekon', 'Sudah Lurus', 'Proses Periv'],
    ['Perbaikan Fiber Kapuas', 'Cahaya Teleponindo', 'PID-2026-002', 'Po Jasa', '5500012346', 'Phase 2', 'BELUM CT', 'BELUM UT', 'Belum Rekap', '2500000', 'Belum Rekon', 'Belum Lurus', 'Antri Periv'],
    ['Optimalisasi Node Landak', 'Sinergi Utama', 'PID-2026-003', 'Po Full', '5500012347', 'Phase 1', 'SUDAH CT', 'BELUM UT', 'Sudah Rekap', '7050000', 'Sudah Rekon', 'Sudah Lurus', 'Sekuler Ttd'],
];

foreach ($data as $row => $values) {
    foreach ($values as $col => $value) {
        $sheet->setCellValueByColumnAndRow($col + 1, $row + 2, $value);
    }
}

// Save file
$savePath = __DIR__ . '/storage/app/';
if (!is_dir($savePath)) {
    mkdir($savePath, 0755, true);
}

$filename = 'test_import_' . time() . '.xlsx';
$filepath = $savePath . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($filepath);

echo "✅ Test file created!\n";
echo "Filename: " . $filename . "\n";
echo "Path: " . $filepath . "\n";
?>
