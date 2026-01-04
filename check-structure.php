<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== STRUKTUR TABEL PENGGUNA ===\n";
$columns = DB::select('SHOW COLUMNS FROM pengguna');
foreach($columns as $col) {
    echo sprintf("%-30s | %-20s | Key: %-5s | Null: %s\n", 
        $col->Field, 
        $col->Type, 
        $col->Key,
        $col->Null
    );
}

echo "\n=== STRUKTUR TABEL PENAGIHAN ===\n";
try {
    $columns = DB::select('SHOW COLUMNS FROM penagihan');
    foreach($columns as $col) {
        echo sprintf("%-30s | %-20s | Key: %-5s | Null: %s\n", 
            $col->Field, 
            $col->Type, 
            $col->Key,
            $col->Null
        );
    }
} catch (Exception $e) {
    echo "Tabel tidak ditemukan atau error: " . $e->getMessage() . "\n";
}

echo "\n=== STRUKTUR TABEL DATA_PROYEK ===\n";
try {
    $columns = DB::select('SHOW COLUMNS FROM data_proyek');
    foreach($columns as $col) {
        echo sprintf("%-30s | %-20s | Key: %-5s | Null: %s\n", 
            $col->Field, 
            $col->Type, 
            $col->Key,
            $col->Null
        );
    }
} catch (Exception $e) {
    echo "Tabel tidak ditemukan atau error: " . $e->getMessage() . "\n";
}

echo "\n=== STRUKTUR TABEL AKTIVITAS_SISTEM ===\n";
$columns = DB::select('SHOW COLUMNS FROM aktivitas_sistem');
foreach($columns as $col) {
    echo sprintf("%-30s | %-20s | Key: %-5s | Null: %s\n", 
        $col->Field, 
        $col->Type, 
        $col->Key,
        $col->Null
    );
}

echo "\n=== STRUKTUR TABEL TOKEN_AKSES_PRIBADI ===\n";
try {
    $columns = DB::select('SHOW COLUMNS FROM token_akses_pribadi');
    foreach($columns as $col) {
        echo sprintf("%-30s | %-20s | Key: %-5s | Null: %s\n", 
            $col->Field, 
            $col->Type, 
            $col->Key,
            $col->Null
        );
    }
} catch (Exception $e) {
    echo "Tabel tidak ditemukan atau error: " . $e->getMessage() . "\n";
}
