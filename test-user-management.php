<?php
/**
 * [👥 TEST] Script untuk test User Management System
 * Verifikasi:
 * 1. GET /api/users - Ambil semua users
 * 2. DELETE /api/users/{id} - Hapus user
 * 3. Validasi data di database
 */

// Konfigurasi
$baseUrl = 'http://localhost:8000/api';
$token = ''; // Isi dengan token Super Admin dari login

// Warna untuk output
function colorize($text, $color = 'green') {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

function printSection($title) {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo colorize($title, 'blue') . "\n";
    echo str_repeat("=", 70) . "\n";
}

function makeRequest($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// ==========================================
// 1. Test GET Users
// ==========================================
printSection("TEST 1: GET /api/users");

if (empty($token)) {
    echo colorize("❌ ERROR: Token tidak diset. Silakan login terlebih dahulu dan isi variabel \$token", 'red') . "\n";
    echo colorize("Cara mendapatkan token:", 'yellow') . "\n";
    echo "1. Login sebagai Super Admin\n";
    echo "2. Copy token dari response\n";
    echo "3. Paste ke variabel \$token di line 13\n";
    exit(1);
}

$result = makeRequest($baseUrl . '/users', 'GET', null, $token);

echo "HTTP Code: " . colorize($result['code'], $result['code'] == 200 ? 'green' : 'red') . "\n";

if ($result['code'] == 200 && isset($result['body']['data'])) {
    $users = $result['body']['data'];
    echo colorize("✓ Berhasil mengambil data users", 'green') . "\n";
    echo "Total users: " . count($users) . "\n\n";
    
    echo "Daftar Users:\n";
    echo str_pad("ID", 5) . str_pad("Name", 25) . str_pad("Email", 30) . str_pad("Role", 15) . "Username\n";
    echo str_repeat("-", 90) . "\n";
    
    foreach ($users as $user) {
        echo str_pad($user['id'] ?? 'N/A', 5);
        echo str_pad(substr($user['name'] ?? 'N/A', 0, 23), 25);
        echo str_pad(substr($user['email'] ?? 'N/A', 0, 28), 30);
        echo str_pad($user['role'] ?? 'N/A', 15);
        echo ($user['username'] ?? 'N/A') . "\n";
    }
    
    // Validasi struktur data
    echo "\n" . colorize("Validasi Struktur Data:", 'yellow') . "\n";
    $firstUser = $users[0] ?? null;
    if ($firstUser) {
        $requiredFields = ['id', 'name', 'username', 'email', 'role', 'created_at'];
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $firstUser)) {
                $missingFields[] = $field;
            }
        }
        
        if (empty($missingFields)) {
            echo colorize("✓ Semua field required ada", 'green') . "\n";
        } else {
            echo colorize("❌ Missing fields: " . implode(', ', $missingFields), 'red') . "\n";
        }
        
        // Validasi tipe data ID
        if (is_numeric($firstUser['id'])) {
            echo colorize("✓ Field 'id' adalah numeric", 'green') . "\n";
        } else {
            echo colorize("❌ Field 'id' bukan numeric: " . gettype($firstUser['id']), 'red') . "\n";
        }
    }
} else {
    echo colorize("❌ Gagal mengambil data users", 'red') . "\n";
    if (isset($result['body']['message'])) {
        echo "Error: " . $result['body']['message'] . "\n";
    }
    print_r($result['body']);
}

// ==========================================
// 2. Test DELETE User (Optional - uncomment jika ingin test)
// ==========================================
// printSection("TEST 2: DELETE /api/users/{id}");
// 
// echo colorize("⚠️  Warning: Test ini akan menghapus user dari database!", 'yellow') . "\n";
// echo "Uncomment kode ini dan isi userId jika ingin test delete\n";
// 
// $userIdToDelete = 0; // Isi dengan ID user yang ingin dihapus (HATI-HATI!)
// 
// if ($userIdToDelete > 0) {
//     echo "Menghapus user ID: $userIdToDelete\n";
//     $result = makeRequest($baseUrl . '/users/' . $userIdToDelete, 'DELETE', null, $token);
//     
//     echo "HTTP Code: " . colorize($result['code'], $result['code'] == 200 ? 'green' : 'red') . "\n";
//     
//     if ($result['code'] == 200) {
//         echo colorize("✓ User berhasil dihapus", 'green') . "\n";
//         echo "Message: " . ($result['body']['message'] ?? '') . "\n";
//         
//         // Verifikasi dengan GET lagi
//         echo "\nVerifikasi user terhapus...\n";
//         $verify = makeRequest($baseUrl . '/users', 'GET', null, $token);
//         if ($verify['code'] == 200) {
//             $userStillExists = false;
//             foreach ($verify['body']['data'] as $user) {
//                 if ($user['id'] == $userIdToDelete) {
//                     $userStillExists = true;
//                     break;
//                 }
//             }
//             
//             if (!$userStillExists) {
//                 echo colorize("✓ User terkonfirmasi terhapus dari sistem", 'green') . "\n";
//             } else {
//                 echo colorize("❌ User masih ada di sistem!", 'red') . "\n";
//             }
//         }
//     } else {
//         echo colorize("❌ Gagal menghapus user", 'red') . "\n";
//         if (isset($result['body']['message'])) {
//             echo "Error: " . $result['body']['message'] . "\n";
//         }
//     }
// }

// ==========================================
// Summary
// ==========================================
printSection("SUMMARY");
echo colorize("✓ Test selesai", 'green') . "\n";
echo "\nCatatan:\n";
echo "1. Pastikan backend server berjalan di http://localhost:8000\n";
echo "2. Pastikan Anda login sebagai Super Admin\n";
echo "3. Untuk test DELETE, uncomment section TEST 2\n";
echo "4. Periksa database langsung untuk memastikan data terhapus\n";
