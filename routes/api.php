<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PenagihanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\MitraController;
use App\Http\Controllers\AktivitasController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\LogAktivitasController;
use App\Http\Controllers\DataCleanupController;
use App\Http\Controllers\PenagihanFilterOptionsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ========================================
// [\ud83d\udd10 AUTH_SYSTEM] PUBLIC ROUTES (No Authentication)
// ========================================
// Throttle untuk mengurangi brute force / abuse
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/super-admin-contact', [ProfileController::class, 'superAdminContact'])->middleware('throttle:30,1');

// ========================================
// [\ud83d\udd10 AUTH_SYSTEM] PROTECTED ROUTES (Authentication Required)
// ========================================
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // ========================================
    // [\ud83d\udd10 AUTH] Basic Auth Routes (All Authenticated Users)
    // ========================================
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ========================================
    // [\ud83d\udd10 PROFILE]
    // - viewer: can update limited fields + upload photo
    // - admin & super_admin: can update profile / photo
    // - super_admin: can change password
    // ========================================
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    // Alias untuk environment yang memblok method PUT
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);

    // Ganti password hanya untuk Super Admin
    Route::middleware('role:super_admin')->group(function () {
        Route::put('/change-password', [ProfileController::class, 'changePassword']);
        Route::put('/profile/change-password', [ProfileController::class, 'changePassword']); // Alias
    });

    // ========================================
    // [\ud83d\udd10 USER_MANAGEMENT] User Management Routes (SUPER ADMIN ONLY)
    // Register user baru HANYA bisa dilakukan oleh Super Admin
    // ========================================
    Route::middleware('role:super_admin')->group(function () {
        // Register user baru (moved from public to protected)
        Route::post('/register', [AuthController::class, 'register']);
        
        Route::prefix('users')->group(function () {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::put('/{id}', [UserManagementController::class, 'update']);
            Route::put('/{id}/active', [UserManagementController::class, 'setActive']);
            Route::put('/{id}/reset-password', [UserManagementController::class, 'resetUserPassword']);
            Route::put('/{id}/role', [UserManagementController::class, 'updateRole']);
            Route::delete('/{id}', [UserManagementController::class, 'destroy']);
        });
    });

    // Dropdown mitra dinamis untuk filter & user setup (super_admin & admin)
    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/mitra-options', [MitraController::class, 'options']);
    });

    // ========================================
    // [\ud83d\udd10 PENAGIHAN] Billing/Collection Routes
    // ✅ IMPORTANT: Specific routes MUST come before {id} wildcard!
    // ========================================
    Route::prefix('penagihan')->group(function () {
        // 1️⃣ Specific routes (non-parameterized)
        Route::get('/statistics', [PenagihanController::class, 'statistics']);
        Route::get('/card-statistics', [PenagihanController::class, 'cardStatistics']);

        // 🎯 Auto Prioritize (SUPER ADMIN ONLY)
        Route::middleware('role:super_admin')->group(function () {
            Route::post('/auto-prioritize', [PenagihanController::class, 'autoPrioritize']);
        });

        // 2️⃣ Export/Download routes (super_admin & admin)
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::get('/export', [PenagihanController::class, 'export']);
            Route::get('/template', [PenagihanController::class, 'downloadTemplate']);
        });

        // 3️⃣ Import route (super_admin & admin)
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::post('/import', [PenagihanController::class, 'import'])->middleware('throttle:5,1');
        });

        // 4️⃣ Bulk Delete - Hapus semua data (super_admin & admin)
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::delete('/delete-all', [PenagihanController::class, 'destroyAll'])->middleware('throttle:5,1');
            Route::delete('/delete-selected', [PenagihanController::class, 'destroySelected'])->middleware('throttle:10,1');
            Route::put('/prioritize-selected', [PenagihanController::class, 'setPrioritizeSelected'])->middleware('throttle:10,1');
        });

        // 4.5️⃣ Filter options (Mitra/Jenis PO/Phase) - admin/super_admin only
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::get('/filter-options', [PenagihanFilterOptionsController::class, 'options']);
        });

        // Read access (hanya role yang dikenali)
        Route::middleware('role:super_admin,admin,viewer')->group(function () {
            Route::get('/', [PenagihanController::class, 'index']);
            Route::get('/{id}', [PenagihanController::class, 'show']);
        });

        // Write access (hanya super_admin & admin)
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::post('/', [PenagihanController::class, 'store']);
            Route::put('/{id}', [PenagihanController::class, 'update']);
            Route::delete('/{id}', [PenagihanController::class, 'destroy']);
            Route::put('/{id}/prioritize', [PenagihanController::class, 'setPrioritize']);
            Route::put('/{id}/timer-complete', [PenagihanController::class, 'setTimerComplete']);
        });

        // NOTE: wildcard {id} sudah dipindah ke group read-only di atas.
    });

    // ========================================
    // [\ud83d\udd14 NOTIFIKASI] Notification Routes (super_admin & admin)
    // ========================================
    Route::middleware('role:super_admin,admin')->prefix('notifikasi')->group(function () {
        Route::get('/', [NotifikasiController::class, 'index']);
        Route::patch('/{id}/read', [NotifikasiController::class, 'markAsRead']);
        Route::delete('/{id}', [NotifikasiController::class, 'destroy']);
    });

    // ========================================
    // [\ud83d\udd0d LOG AKTIVITAS] Access & Navigation Logs (SUPER ADMIN ONLY)
    // ========================================
    Route::middleware('role:super_admin')->prefix('log-aktivitas')->group(function () {
        Route::get('/', [LogAktivitasController::class, 'index']);
    });

    // ========================================
    // [\ud83d\udd10 ACTIVITY] Activity Log Routes (SUPER ADMIN ONLY)
    // ========================================
    Route::middleware('role:super_admin')->prefix('aktivitas')->group(function () {
        Route::get('/', [AktivitasController::class, 'index']);
        Route::get('/{id}', [AktivitasController::class, 'show']);
    });

    // Alias endpoint for frontend compatibility
    Route::middleware('role:super_admin')->prefix('aktivitas-sistem')->group(function () {
        Route::get('/', [AktivitasController::class, 'index']);
        Route::get('/{id}', [AktivitasController::class, 'show']);
    });

    // ========================================
    // [🗑️ DATA CLEANUP] Data Cleanup Routes (SUPER ADMIN ONLY)
    // ========================================
    Route::middleware('role:super_admin')->prefix('cleanup')->group(function () {
        Route::get('/available-years', [DataCleanupController::class, 'getAvailableYears']);
        Route::post('/stats', [DataCleanupController::class, 'getCleanupStats']);
        Route::delete('/aktivitas-sistem', [DataCleanupController::class, 'cleanupAktivitasSistem']);
        Route::delete('/log-aktivitas', [DataCleanupController::class, 'cleanupLogAktivitas']);
        Route::delete('/notifikasi', [DataCleanupController::class, 'cleanupNotifikasi']);
        Route::delete('/all', [DataCleanupController::class, 'cleanupAll']);
    });
});
