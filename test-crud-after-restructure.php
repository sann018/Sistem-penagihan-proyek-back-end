<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Penagihan;
use App\Models\AktivitasSistem;

echo "=== TESTING CRUD OPERATIONS ===\n\n";

// Test 1: User (id_pengguna)
echo "1. TEST MODEL USER (Primary Key: id_pengguna)\n";
try {
    $user = User::first();
    if ($user) {
        echo "   ✓ User ditemukan: {$user->nama}\n";
        echo "   ✓ Primary Key: {$user->getKeyName()} = {$user->getKey()}\n";
    } else {
        echo "   ⚠ Tidak ada user di database\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Data Proyek (pid)
echo "\n2. TEST MODEL PENAGIHAN/DATA_PROYEK (Primary Key: pid)\n";
try {
    $proyek = Penagihan::first();
    if ($proyek) {
        echo "   ✓ Proyek ditemukan: {$proyek->nama_proyek}\n";
        echo "   ✓ Primary Key: {$proyek->getKeyName()} = {$proyek->getKey()}\n";
        echo "   ✓ Tabel: {$proyek->getTable()}\n";
        echo "   ✓ Incrementing: " . ($proyek->getIncrementing() ? 'Yes' : 'No') . "\n";
        echo "   ✓ Key Type: {$proyek->getKeyType()}\n";
    } else {
        echo "   ⚠ Tidak ada proyek di database\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Aktivitas Sistem (id_aktivitas)
echo "\n3. TEST MODEL AKTIVITAS_SISTEM (Primary Key: id_aktivitas)\n";
try {
    $aktivitas = AktivitasSistem::first();
    if ($aktivitas) {
        echo "   ✓ Aktivitas ditemukan: {$aktivitas->aksi}\n";
        echo "   ✓ Primary Key: {$aktivitas->getKeyName()} = {$aktivitas->getKey()}\n";
    } else {
        echo "   ⚠ Tidak ada aktivitas di database\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Count Records
echo "\n4. TEST COUNT RECORDS\n";
try {
    echo "   ✓ Total Pengguna: " . User::count() . "\n";
    echo "   ✓ Total Data Proyek: " . Penagihan::count() . "\n";
    echo "   ✓ Total Aktivitas: " . AktivitasSistem::count() . "\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Find by Primary Key
echo "\n5. TEST FIND BY PRIMARY KEY\n";
try {
    if ($user) {
        $foundUser = User::find($user->id_pengguna);
        echo "   ✓ User::find() berhasil: " . ($foundUser ? $foundUser->nama : 'null') . "\n";
    }
    
    if ($proyek) {
        $foundProyek = Penagihan::find($proyek->pid);
        echo "   ✓ Penagihan::find() berhasil: " . ($foundProyek ? $foundProyek->nama_proyek : 'null') . "\n";
    }
    
    if ($aktivitas) {
        $foundAktivitas = AktivitasSistem::find($aktivitas->id_aktivitas);
        echo "   ✓ AktivitasSistem::find() berhasil: " . ($foundAktivitas ? $foundAktivitas->aksi : 'null') . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== SEMUA TEST SELESAI ===\n";
