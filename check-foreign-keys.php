<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== FOREIGN KEY RELATIONSHIPS ===\n\n";

$fks = DB::select("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME, COLUMN_NAME
");

if (empty($fks)) {
    echo "❌ Tidak ada foreign key ditemukan\n\n";
} else {
    foreach ($fks as $fk) {
        echo "📌 {$fk->TABLE_NAME}.{$fk->COLUMN_NAME}\n";
        echo "   -> {$fk->REFERENCED_TABLE_NAME}.{$fk->REFERENCED_COLUMN_NAME}\n";
        echo "   Constraint: {$fk->CONSTRAINT_NAME}\n\n";
    }
}

// Cek apakah PID digunakan sebagai FK
echo "\n=== CEK: Apakah PID digunakan sebagai FOREIGN KEY? ===\n\n";

$pidAsFk = DB::select("
    SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND REFERENCED_TABLE_NAME = 'data_proyek'
    AND REFERENCED_COLUMN_NAME = 'pid'
");

if (empty($pidAsFk)) {
    echo "✅ PID TIDAK digunakan sebagai FK di tabel lain\n";
    echo "   Status: AMAN untuk dijadikan PRIMARY KEY\n\n";
} else {
    echo "⚠️  PID digunakan sebagai FK di tabel:\n";
    foreach ($pidAsFk as $fk) {
        echo "   - {$fk->TABLE_NAME}.{$fk->COLUMN_NAME} (constraint: {$fk->CONSTRAINT_NAME})\n";
    }
    echo "\n   Status: Perlu dianalisis lebih lanjut\n\n";
}

// Cek struktur tabel data_proyek
echo "=== STRUKTUR TABEL data_proyek ===\n\n";
$columns = DB::select("SHOW FULL COLUMNS FROM data_proyek");

foreach ($columns as $col) {
    if (in_array($col->Field, ['pid', 'nama_proyek', 'nama_mitra', 'status', 'prioritas'])) {
        echo "📋 {$col->Field}\n";
        echo "   Type: {$col->Type}\n";
        echo "   Null: {$col->Null}\n";
        echo "   Key: {$col->Key}\n";
        echo "   Default: " . ($col->Default ?? 'NULL') . "\n";
        echo "   Extra: {$col->Extra}\n\n";
    }
}
