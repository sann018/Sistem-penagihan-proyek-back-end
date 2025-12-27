<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "🔍 TEST FOTO PROFIL DI AKTIVITAS\n";
echo "========================================\n\n";

// Get users with photos
$usersWithPhotos = \App\Models\User::whereNotNull('foto')->get();
echo "📊 Users dengan foto: " . $usersWithPhotos->count() . "\n\n";

foreach ($usersWithPhotos as $user) {
    echo "User ID: {$user->id}\n";
    echo "Nama: {$user->nama}\n";
    echo "Foto (raw): {$user->foto}\n";
    echo "Foto URL: " . url('storage/' . $user->foto) . "\n";
    echo "File exists: " . (file_exists(storage_path('app/public/' . $user->foto)) ? '✅ YES' : '❌ NO') . "\n";
    echo "----------------------------------------\n";
}

echo "\n========================================\n";
echo "SAMPLE AKTIVITAS:\n";
echo "========================================\n\n";

// Get sample activities with foto_profile
$activities = \App\Models\AktivitasSistem::with('pengguna')
    ->orderBy('waktu_aksi', 'desc')
    ->limit(5)
    ->get();

foreach ($activities as $activity) {
    echo "Activity ID: {$activity->id}\n";
    echo "User: {$activity->nama_pengguna}\n";
    echo "Action: {$activity->aksi}\n";
    
    if ($activity->pengguna) {
        $fotoRaw = $activity->pengguna->foto;
        $fotoUrl = $fotoRaw ? url('storage/' . $fotoRaw) : null;
        
        echo "Foto (raw): " . ($fotoRaw ?? 'NULL') . "\n";
        echo "Foto URL: " . ($fotoUrl ?? 'NULL') . "\n";
        
        if ($fotoRaw) {
            $filePath = storage_path('app/public/' . $fotoRaw);
            echo "File exists: " . (file_exists($filePath) ? '✅ YES' : '❌ NO') . "\n";
            if (file_exists($filePath)) {
                echo "File size: " . filesize($filePath) . " bytes\n";
            }
        }
    } else {
        echo "⚠️ User not found!\n";
    }
    
    echo "----------------------------------------\n";
}

echo "\n========================================\n";
echo "✅ TEST COMPLETED\n";
echo "========================================\n";
