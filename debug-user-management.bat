@echo off
chcp 65001 >nul
echo ========================================
echo 🔍 DEBUG USER MANAGEMENT SYSTEM
echo ========================================
echo.

REM Cek Laravel server
echo [1/5] Cek Laravel server...
curl -s http://localhost:8000/api/user 2>nul >nul
if errorlevel 1 (
    echo ❌ Laravel server TIDAK berjalan!
    echo.
    echo Jalankan dulu: php artisan serve
    pause
    exit /b 1
) else (
    echo ✓ Laravel server OK
)

echo.
echo [2/5] Cek database users...
echo.

REM Jalankan query ke database
php -r "
try {
    require 'vendor/autoload.php';
    \$app = require_once 'bootstrap/app.php';
    \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
    \$kernel->bootstrap();
    
    \$users = \App\Models\User::all();
    echo '✓ Total users di database: ' . \$users->count() . PHP_EOL;
    echo PHP_EOL;
    
    if (\$users->count() > 0) {
        echo 'Sample user (RAW dari database):' . PHP_EOL;
        echo '================================' . PHP_EOL;
        \$first = \$users->first();
        echo 'id_pengguna: ' . \$first->id_pengguna . PHP_EOL;
        echo 'nama: ' . \$first->nama . PHP_EOL;
        echo 'username: ' . \$first->username . PHP_EOL;
        echo 'email: ' . \$first->email . PHP_EOL;
        echo 'peran: ' . \$first->peran . PHP_EOL;
        echo 'nomor_hp: ' . (\$first->nomor_hp ?? 'NULL') . PHP_EOL;
        echo 'jobdesk: ' . (\$first->jobdesk ?? 'NULL') . PHP_EOL;
        echo 'mitra: ' . (\$first->mitra ?? 'NULL') . PHP_EOL;
        echo 'foto: ' . (\$first->foto ?? 'NULL') . PHP_EOL;
        echo PHP_EOL;
        
        echo 'Test Accessor getIdAttribute():' . PHP_EOL;
        echo '================================' . PHP_EOL;
        echo '\$user->id = ' . \$first->id . PHP_EOL;
        echo '\$user->id_pengguna = ' . \$first->id_pengguna . PHP_EOL;
        echo PHP_EOL;
    } else {
        echo '❌ Tidak ada user di database!' . PHP_EOL;
        echo PHP_EOL;
        echo 'Buat user baru dengan:' . PHP_EOL;
        echo 'php artisan db:seed --class=UserSeeder' . PHP_EOL;
    }
} catch (Exception \$e) {
    echo '❌ Error: ' . \$e->getMessage() . PHP_EOL;
}
"

echo.
echo [3/5] Test API Endpoint tanpa auth...
echo.

curl -s -X GET "http://localhost:8000/api/users" ^
  -H "Accept: application/json" ^
  -H "Content-Type: application/json"

echo.
echo.
echo ========================================
echo [4/5] Test dengan Token
echo ========================================
echo.
set /p TOKEN="Masukkan token Super Admin (atau Enter untuk skip): "

if "%TOKEN%"=="" (
    echo.
    echo ⚠️  Skipped - Tidak ada token
    echo.
    echo Cara mendapatkan token:
    echo 1. Login sebagai Super Admin
    echo 2. Buka Console Browser ^(F12^)
    echo 3. Cari di Network tab atau localStorage
    goto :summary
)

echo.
echo Testing dengan token...
echo.

curl -s -X GET "http://localhost:8000/api/users" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Accept: application/json" ^
  -H "Content-Type: application/json" > response.json

type response.json
echo.

REM Parse JSON untuk cek struktur
php -r "
\$json = file_get_contents('response.json');
\$data = json_decode(\$json, true);

if (\$data === null) {
    echo PHP_EOL . '❌ Response bukan JSON valid!' . PHP_EOL;
} else {
    echo PHP_EOL . '✓ Response JSON valid' . PHP_EOL;
    echo PHP_EOL . 'Struktur:' . PHP_EOL;
    echo '========' . PHP_EOL;
    echo 'success: ' . (isset(\$data['success']) ? (\$data['success'] ? 'true' : 'false') : 'MISSING') . PHP_EOL;
    echo 'message: ' . (\$data['message'] ?? 'MISSING') . PHP_EOL;
    echo 'data: ' . (isset(\$data['data']) ? (is_array(\$data['data']) ? count(\$data['data']) . ' items' : 'NOT ARRAY') : 'MISSING') . PHP_EOL;
    
    if (isset(\$data['data']) && is_array(\$data['data']) && count(\$data['data']) > 0) {
        echo PHP_EOL . 'Sample item dari response:' . PHP_EOL;
        echo '=========================' . PHP_EOL;
        \$first = \$data['data'][0];
        foreach (\$first as \$key => \$value) {
            if (is_string(\$value) || is_numeric(\$value) || is_bool(\$value) || is_null(\$value)) {
                echo str_pad(\$key, 15) . ': ' . var_export(\$value, true) . PHP_EOL;
            }
        }
    }
}
"

del response.json 2>nul

:summary
echo.
echo ========================================
echo [5/5] SUMMARY & TROUBLESHOOTING
echo ========================================
echo.
echo Langkah-langkah debug:
echo.
echo 1. Pastikan ada data di database
echo    - Cek output [2/5] di atas
echo.
echo 2. Pastikan token valid
echo    - Login ulang jika perlu
echo    - Token ada di localStorage browser
echo.
echo 3. Cek Console Browser ^(F12^)
echo    - Buka tab Console
echo    - Lihat log "[UserManagement]"
echo    - Lihat error di Network tab
echo.
echo 4. Cek Laravel log
echo    - storage/logs/laravel.log
echo    - Cari error terbaru
echo.
echo 5. Test langsung
echo    - Buka: http://localhost:3000
echo    - Login sebagai Super Admin
echo    - Buka User Management
echo    - F12 → Console → Lihat log
echo.
echo ========================================
pause
