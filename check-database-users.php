<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "========================================\n";
echo "DATABASE CHECK - Users Table\n";
echo "========================================\n\n";

$users = \App\Models\User::all();
echo "Total users di database: " . $users->count() . "\n\n";

if ($users->count() > 0) {
    echo "Daftar Users:\n";
    echo str_repeat("-", 80) . "\n";
    echo str_pad("ID", 5) . str_pad("Nama", 25) . str_pad("Email", 30) . str_pad("Role", 15) . "\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($users as $user) {
        echo str_pad($user->id_pengguna, 5);
        echo str_pad(substr($user->nama, 0, 23), 25);
        echo str_pad(substr($user->email, 0, 28), 30);
        echo str_pad($user->peran, 15);
        echo "\n";
    }
    
    echo "\n\nSample user detail (first user):\n";
    echo str_repeat("=", 80) . "\n";
    $first = $users->first();
    echo "id_pengguna: " . $first->id_pengguna . "\n";
    echo "nama: " . $first->nama . "\n";
    echo "username: " . ($first->username ?? 'NULL') . "\n";
    echo "email: " . $first->email . "\n";
    echo "peran: " . $first->peran . "\n";
    echo "nomor_hp: " . ($first->nomor_hp ?? 'NULL') . "\n";
    echo "jobdesk: " . ($first->jobdesk ?? 'NULL') . "\n";
    echo "mitra: " . ($first->mitra ?? 'NULL') . "\n";
    echo "foto: " . ($first->foto ?? 'NULL') . "\n";
    echo "dibuat_pada: " . $first->dibuat_pada . "\n";
    
    echo "\n\nTest Accessor:\n";
    echo str_repeat("=", 80) . "\n";
    echo "\$user->id (accessor): " . $first->id . "\n";
    echo "\$user->id_pengguna (original): " . $first->id_pengguna . "\n";
    echo "\$user->role (accessor): " . $first->role . "\n";
    echo "\$user->peran (original): " . $first->peran . "\n";
    
    echo "\n\n✅ Database OK - Ada " . $users->count() . " user(s)\n";
} else {
    echo "❌ TIDAK ADA USER di database!\n\n";
    echo "Silakan buat user dengan salah satu cara:\n";
    echo "1. php artisan db:seed --class=UserSeeder\n";
    echo "2. Daftar manual via aplikasi\n";
}

echo "\n========================================\n";
