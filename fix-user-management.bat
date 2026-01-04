@echo off
chcp 65001 >nul
color 0A
cls

echo.
echo ╔════════════════════════════════════════════════════════════════╗
echo ║                                                                ║
echo ║   🚀 FIX DATA USER TIDAK MUNCUL - ALL IN ONE TOOL           ║
echo ║                                                                ║
echo ╚════════════════════════════════════════════════════════════════╝
echo.
echo.

:menu
echo ┌────────────────────────────────────────────────────────────────┐
echo │  Pilih Aksi:                                                   │
echo ├────────────────────────────────────────────────────────────────┤
echo │  1. 🔍 Check Database (Lihat data users)                       │
echo │  2. 🧪 Test API (Test endpoint /users)                         │
echo │  3. 🌐 Buka Debug Tool Browser                                 │
echo │  4. 📊 Run Full Diagnostic                                     │
echo │  5. 🔄 Restart Backend Server                                  │
echo │  6. 🔄 Restart Frontend Server                                 │
echo │  7. 🗑️  Clear Cache Laravel                                     │
echo │  8. 📖 Buka Dokumentasi                                        │
echo │  0. ❌ Exit                                                     │
echo └────────────────────────────────────────────────────────────────┘
echo.

set /p choice="Pilih nomor (0-8): "

if "%choice%"=="1" goto check_db
if "%choice%"=="2" goto test_api
if "%choice%"=="3" goto open_debug
if "%choice%"=="4" goto full_diagnostic
if "%choice%"=="5" goto restart_backend
if "%choice%"=="6" goto restart_frontend
if "%choice%"=="7" goto clear_cache
if "%choice%"=="8" goto open_docs
if "%choice%"=="0" goto exit

echo.
echo ❌ Pilihan tidak valid!
timeout /t 2 >nul
cls
goto menu

:check_db
cls
echo.
echo ═══════════════════════════════════════════════════════════════
echo  🔍 CHECK DATABASE
echo ═══════════════════════════════════════════════════════════════
echo.
php check-database-users.php
echo.
echo ═══════════════════════════════════════════════════════════════
pause
cls
goto menu

:test_api
cls
echo.
echo ═══════════════════════════════════════════════════════════════
echo  🧪 TEST API
echo ═══════════════════════════════════════════════════════════════
echo.
php test-api-users-auto.php
echo.
echo ═══════════════════════════════════════════════════════════════
pause
cls
goto menu

:open_debug
cls
echo.
echo ═══════════════════════════════════════════════════════════════
echo  🌐 MEMBUKA DEBUG TOOL
echo ═══════════════════════════════════════════════════════════════
echo.
echo  Membuka browser...
start http://localhost:8000/debug-user-management.html
echo.
echo  ✅ Debug tool dibuka di browser!
echo.
echo  Instruksi:
echo  1. Login ke aplikasi sebagai Super Admin
echo  2. Klik "Ambil dari localStorage"
echo  3. Klik "Run Full Diagnostic"
echo.
echo ═══════════════════════════════════════════════════════════════
pause
cls
goto menu

:full_diagnostic
cls
echo.
echo ═══════════════════════════════════════════════════════════════
echo  📊 FULL DIAGNOSTIC
echo ═══════════════════════════════════════════════════════════════
echo.
call debug-user-management.bat
cls
goto menu

:restart_backend
cls
echo.
echo ═══════════════════════════════════════════════════════════════
echo  🔄 RESTART BACKEND SERVER
echo ═══════════════════════════════════════════════════════════════
echo.
echo  Menghentikan server yang sedang berjalan...
taskkill /F /IM php.exe >nul 2>&1
timeout /t 2 >nul
echo  ✅ Server dihentikan
echo.
echo  Memulai server baru...
echo  URL: http://localhost:8000
echo.
start cmd /k "cd /d d:\laragon\www\sistem-monitoring-penagihan-back-end && php artisan serve"
timeout /t 3 >nul
echo  ✅ Backend server started!
echo.
echo ═══════════════════════════════════════════════════════════════
pause
cls
goto menu

:restart_frontend
cls
echo.
echo ═══════════════════════════════════════════════════════════════
echo  🔄 RESTART FRONTEND SERVER
echo ═══════════════════════════════════════════════════════════════
echo.
echo  Menghentikan server yang sedang berjalan...
taskkill /F /IM node.exe >nul 2>&1
timeout /t 2 >nul
echo  ✅ Server dihentikan
echo.
echo  Memulai server baru...
echo  URL: http://localhost:5173 (atau port lainnya)
echo.
start cmd /k "cd /d d:\laragon\www\sistem-monitoring-penagihan-front-end && npm run dev"
timeout /t 3 >nul
echo  ✅ Frontend server started!
echo.
echo ═══════════════════════════════════════════════════════════════
pause
cls
goto menu

:clear_cache
cls
echo.
echo ═══════════════════════════════════════════════════════════════
echo  🗑️  CLEAR CACHE
echo ═══════════════════════════════════════════════════════════════
echo.
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
echo.
echo  ✅ Cache Laravel berhasil dibersihkan!
echo.
echo ═══════════════════════════════════════════════════════════════
pause
cls
goto menu

:open_docs
cls
echo.
echo ═══════════════════════════════════════════════════════════════
echo  📖 DOKUMENTASI
echo ═══════════════════════════════════════════════════════════════
echo.
echo  Membuka dokumentasi...
echo.
start notepad.exe "d:\laragon\www\QUICK_FIX_USER_MANAGEMENT.md"
start notepad.exe "d:\laragon\www\TROUBLESHOOTING_USER_MANAGEMENT.md"
timeout /t 2 >nul
echo  ✅ Dokumentasi dibuka!
echo.
echo ═══════════════════════════════════════════════════════════════
pause
cls
goto menu

:exit
cls
echo.
echo ╔════════════════════════════════════════════════════════════════╗
echo ║                                                                ║
echo ║   ✅ Terima kasih sudah menggunakan tool ini!                 ║
echo ║                                                                ║
echo ║   Tips:                                                        ║
echo ║   - Pastikan backend & frontend tetap berjalan                ║
echo ║   - Cek Console Browser (F12) untuk debug                     ║
echo ║   - Hard refresh browser: Ctrl + Shift + R                    ║
echo ║                                                                ║
echo ╚════════════════════════════════════════════════════════════════╝
echo.
timeout /t 3 >nul
exit
