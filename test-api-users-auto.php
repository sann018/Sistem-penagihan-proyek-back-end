<?php
// Quick test untuk API /users tanpa perlu token manual
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "========================================\n";
echo "TEST API: GET /api/users\n";
echo "========================================\n\n";

// Cari Super Admin untuk generate token
$superAdmin = \App\Models\User::where('peran', 'super_admin')->first();

if (!$superAdmin) {
    echo "❌ Tidak ada Super Admin di database!\n";
    exit(1);
}

echo "Using Super Admin: " . $superAdmin->nama . " (" . $superAdmin->email . ")\n";

// Generate token
$token = $superAdmin->createToken('test-token')->plainTextToken;
echo "Generated token: " . substr($token, 0, 30) . "...\n\n";

// Test API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/users');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: " . $httpCode . "\n";
echo str_repeat("-", 80) . "\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['data'])) {
        echo "✅ API Response OK\n\n";
        echo "Response structure:\n";
        echo "  - success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "  - message: " . ($data['message'] ?? 'N/A') . "\n";
        echo "  - data count: " . count($data['data']) . " users\n\n";
        
        if (count($data['data']) > 0) {
            echo "Sample user dari API:\n";
            echo str_repeat("=", 80) . "\n";
            $firstUser = $data['data'][0];
            foreach ($firstUser as $key => $value) {
                if (is_string($value) || is_numeric($value) || is_bool($value) || is_null($value)) {
                    echo str_pad($key, 15) . ": " . var_export($value, true) . "\n";
                }
            }
            echo "\n";
            
            // Validasi field required
            $requiredFields = ['id', 'name', 'username', 'email', 'role', 'created_at'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $firstUser)) {
                    $missingFields[] = $field;
                }
            }
            
            if (empty($missingFields)) {
                echo "✅ Semua field required ada\n";
            } else {
                echo "❌ Missing fields: " . implode(', ', $missingFields) . "\n";
            }
            
            // Validasi tipe data
            if (is_numeric($firstUser['id'])) {
                echo "✅ Field 'id' adalah numeric: " . $firstUser['id'] . "\n";
            } else {
                echo "❌ Field 'id' bukan numeric: " . gettype($firstUser['id']) . "\n";
            }
        }
    } else {
        echo "❌ Response format tidak valid\n";
        echo "Raw response:\n";
        echo $response . "\n";
    }
} else {
    echo "❌ API Error (HTTP " . $httpCode . ")\n";
    echo "Raw response:\n";
    echo $response . "\n";
}

// Cleanup token
$superAdmin->tokens()->where('name', 'test-token')->delete();

echo "\n========================================\n";
echo "✅ Test selesai\n";
echo "========================================\n";
