<?php
/**
 * Test script untuk endpoint notifikasi API
 * Jalankan: php test-notifikasi-api.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST NOTIFIKASI API ===\n\n";

// Get user admin/super_admin
$user = \App\Models\User::whereIn('peran', ['super_admin', 'admin'])->first();

if (!$user) {
    echo "❌ Tidak ada user admin/super_admin di database\n";
    exit(1);
}

echo "✅ Test dengan user: {$user->nama} (ID: {$user->id_pengguna}, Role: {$user->peran})\n\n";

// Create token for auth
$token = $user->createToken('test-token')->plainTextToken;
echo "✅ Token dibuat: " . substr($token, 0, 30) . "...\n\n";

// Test endpoint
$baseUrl = 'http://localhost:8000/api';
echo "🌐 Base URL: {$baseUrl}\n\n";

// Test 1: Get notifications
echo "--- TEST 1: GET /api/notifikasi ---\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/notifikasi?per_page=5");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
echo "Response:\n";
echo $response . "\n\n";

if ($httpCode !== 200) {
    echo "❌ Error: Expected HTTP 200, got {$httpCode}\n";
    
    // Cek database langsung
    echo "\n--- Cek database langsung ---\n";
    $count = \App\Models\Notifikasi::where('id_penerima', $user->id_pengguna)->count();
    echo "Jumlah notifikasi untuk user ini: {$count}\n";
    
    if ($count > 0) {
        $notif = \App\Models\Notifikasi::where('id_penerima', $user->id_pengguna)->first();
        echo "Sample notifikasi:\n";
        echo "  - ID: {$notif->id_notifikasi}\n";
        echo "  - Judul: {$notif->judul}\n";
        echo "  - Jenis: {$notif->jenis_notifikasi}\n";
        echo "  - Status: {$notif->status}\n";
    }
} else {
    echo "✅ API berfungsi dengan baik\n";
    
    $data = json_decode($response, true);
    if (isset($data['data'])) {
        echo "Total notifikasi: " . count($data['data']) . "\n";
    }
}

echo "\n=== TEST SELESAI ===\n";
