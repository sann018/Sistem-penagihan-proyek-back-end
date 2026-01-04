<?php

require __DIR__ . '/vendor/autoload.php';

use Carbon\Carbon;

echo "========================================\n";
echo "Testing Cleanup Filter Logic\n";
echo "========================================\n\n";

// Test January 2026
$bulan = 1;
$tahun = 2026;

echo "Testing for: Bulan $bulan, Tahun $tahun\n\n";

// OLD Logic (using <=)
$batasWaktu = Carbon::create($tahun, $bulan, 1)->endOfMonth();
echo "OLD LOGIC (<=):\n";
echo "  Cutoff Date: " . $batasWaktu->format('Y-m-d H:i:s') . "\n";
echo "  Query: WHERE waktu_kejadian <= '{$batasWaktu->format('Y-m-d H:i:s')}'\n";
echo "  Result: Would delete ALL data from beginning of time up to {$batasWaktu->format('Y-m-d')}\n\n";

// NEW Logic (using BETWEEN)
$awalBulan = Carbon::create($tahun, $bulan, 1)->startOfMonth();
$akhirBulan = Carbon::create($tahun, $bulan, 1)->endOfMonth();
echo "NEW LOGIC (BETWEEN):\n";
echo "  Start Date: " . $awalBulan->format('Y-m-d H:i:s') . "\n";
echo "  End Date:   " . $akhirBulan->format('Y-m-d H:i:s') . "\n";
echo "  Query: WHERE waktu_kejadian BETWEEN '{$awalBulan->format('Y-m-d H:i:s')}' AND '{$akhirBulan->format('Y-m-d H:i:s')}'\n";
echo "  Result: Would delete ONLY data from {$awalBulan->format('Y-m-d')} to {$akhirBulan->format('Y-m-d')}\n\n";

echo "========================================\n";
echo "Example: If you have 8 records in January 2026\n";
echo "and 1000 records before January 2026:\n";
echo "========================================\n";
echo "OLD Logic would show: 1008 records (DANGEROUS!)\n";
echo "NEW Logic would show: 8 records (CORRECT!)\n";
echo "========================================\n";
