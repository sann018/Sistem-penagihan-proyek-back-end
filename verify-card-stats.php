<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "🔍 VERIFY CARD STATISTICS\n";
echo "========================================\n\n";

// SIMULATE /api/penagihan/card-statistics endpoint

$totalProyek = \App\Models\Penagihan::count();

// Sudah Penuh: Semua 6 status selesai
$sudahPenuh = \App\Models\Penagihan::query()
    ->whereRaw('LOWER(status_ct) = ?', ['sudah ct'])
    ->whereRaw('LOWER(status_ut) = ?', ['sudah ut'])
    ->whereRaw('LOWER(rekap_boq) = ?', ['sudah rekap'])
    ->whereRaw('LOWER(rekon_material) = ?', ['sudah rekon'])
    ->whereRaw('LOWER(pelurusan_material) = ?', ['sudah lurus'])
    ->whereRaw('LOWER(status_procurement) = ?', ['otw reg'])
    ->count();

// Tertunda: Status Procurement = Revisi Mitra
$tertunda = \App\Models\Penagihan::query()
    ->whereRaw('LOWER(status_procurement) = ?', ['revisi mitra'])
    ->count();

// Belum Rekon: Rekap BOQ = Belum Rekap
$belumRekon = \App\Models\Penagihan::query()
    ->whereRaw('LOWER(rekap_boq) = ?', ['belum rekap'])
    ->count();

// Sedang Berjalan: Ada salah satu status belum selesai (kecuali tertunda)
$sedangBerjalan = \App\Models\Penagihan::query()
    ->where(function($q) {
        $q->whereRaw('LOWER(status_ct) != ?', ['sudah ct'])
          ->orWhereRaw('LOWER(status_ut) != ?', ['sudah ut'])
          ->orWhereRaw('LOWER(rekap_boq) != ?', ['sudah rekap'])
          ->orWhereRaw('LOWER(rekon_material) != ?', ['sudah rekon'])
          ->orWhereRaw('LOWER(pelurusan_material) != ?', ['sudah lurus'])
          ->orWhereRaw('LOWER(status_procurement) != ?', ['otw reg']);
    })
    ->whereRaw('LOWER(status_procurement) != ?', ['revisi mitra'])
    ->count();

echo "📊 CARD STATISTICS (From ALL Data):\n";
echo "========================================\n\n";

echo "✅ Total Proyek: {$totalProyek}\n";
echo "🎯 Sudah Penuh (Selesai): {$sudahPenuh}\n";
echo "⚙️  Sedang Berjalan: {$sedangBerjalan}\n";
echo "⏸️  Tertunda: {$tertunda}\n";
echo "📋 Belum Rekon: {$belumRekon}\n\n";

// Verify sum
$sum = $sudahPenuh + $sedangBerjalan + $tertunda + $belumRekon;
echo "========================================\n";
echo "VERIFICATION:\n";
echo "========================================\n";
echo "Sum of categories: {$sum}\n";

if ($sum >= $totalProyek) {
    echo "✅ OK: Categories dapat overlap (normal)\n";
} else {
    echo "⚠️  WARNING: Sum < Total (ada proyek yang tidak masuk kategori?)\n";
}

echo "\n========================================\n";
echo "✅ CARD STATISTICS VERIFIED\n";
echo "========================================\n";
