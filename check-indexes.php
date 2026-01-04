<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CEK INDEXES PADA TABLE data_proyek ===\n\n";

$indexes = DB::select("SHOW INDEX FROM data_proyek");

$grouped = [];
foreach ($indexes as $idx) {
    $grouped[$idx->Key_name][] = $idx;
}

foreach ($grouped as $keyName => $columns) {
    echo "Index: {$keyName}\n";
    echo "  Type: " . ($columns[0]->Index_type ?? 'BTREE') . "\n";
    echo "  Columns: ";
    $cols = array_map(fn($c) => $c->Column_name, $columns);
    echo implode(', ', $cols) . "\n";
    echo "\n";
}

echo "\n=== CEK INDEXES PADA TABLE aktivitas_sistem ===\n\n";

$indexes2 = DB::select("SHOW INDEX FROM aktivitas_sistem");

$grouped2 = [];
foreach ($indexes2 as $idx) {
    $grouped2[$idx->Key_name][] = $idx;
}

foreach ($grouped2 as $keyName => $columns) {
    echo "Index: {$keyName}\n";
    echo "  Type: " . ($columns[0]->Index_type ?? 'BTREE') . "\n";
    echo "  Columns: ";
    $cols = array_map(fn($c) => $c->Column_name, $columns);
    echo implode(', ', $cols) . "\n";
    echo "\n";
}
