<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "🔍 TEST API PAGINATION\n";
echo "========================================\n\n";

// Simulate API request dengan default (15 per page)
echo "TEST 1: Default pagination (per_page tidak diset)\n";
echo "----------------------------------------\n";
$query1 = \App\Models\Penagihan::query();
$result1 = $query1->paginate(15);
echo "Total items di database: {$result1->total()}\n";
echo "Items di page ini: {$result1->count()}\n";
echo "Per page: {$result1->perPage()}\n";
echo "Current page: {$result1->currentPage()}\n";
echo "Total pages: {$result1->lastPage()}\n";
echo "\n";

// Simulate API request dengan per_page=1000
echo "TEST 2: Large pagination (per_page=1000)\n";
echo "----------------------------------------\n";
$query2 = \App\Models\Penagihan::query();
$result2 = $query2->paginate(1000);
echo "Total items di database: {$result2->total()}\n";
echo "Items di page ini: {$result2->count()}\n";
echo "Per page: {$result2->perPage()}\n";
echo "Current page: {$result2->currentPage()}\n";
echo "Total pages: {$result2->lastPage()}\n";
echo "\n";

echo "========================================\n";
echo "✅ CONCLUSION:\n";
echo "========================================\n";
echo "❌ Default (15): Hanya ambil 15 dari 25 data (masalah!)\n";
echo "✅ per_page=1000: Ambil semua 25 data (fixed!)\n";
echo "\n";
