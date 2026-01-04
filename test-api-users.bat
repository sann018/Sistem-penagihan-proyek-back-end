@echo off
echo ========================================
echo TEST API /api/users
echo ========================================
echo.
echo Pastikan:
echo 1. Laravel server berjalan (php artisan serve)
echo 2. Sudah login dan punya token
echo.
echo Tekan Ctrl+C untuk cancel, atau
pause

set /p TOKEN="Masukkan token Super Admin: "

echo.
echo Testing GET /api/users...
echo.

curl -X GET "http://localhost:8000/api/users" ^
  -H "Authorization: Bearer %TOKEN%" ^
  -H "Accept: application/json" ^
  -H "Content-Type: application/json"

echo.
echo.
echo ========================================
echo TEST SELESAI
echo ========================================
pause
