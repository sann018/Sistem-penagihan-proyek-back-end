<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PenagihanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\AktivitasController;

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
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

// ========================================
// [\ud83d\udd10 AUTH_SYSTEM] PROTECTED ROUTES (Authentication Required)
// ========================================
Route::middleware('auth:sanctum')->group(function () {
    // ========================================
    // [\ud83d\udd10 AUTH] Basic Auth Routes (All Authenticated Users)
    // ========================================
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ========================================
    // [\ud83d\udd10 PROFILE] Profile Routes (All Authenticated Users)
    // ========================================
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::put('/change-password', [ProfileController::class, 'changePassword']);
    Route::put('/profile/change-password', [ProfileController::class, 'changePassword']); // Alias

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
            Route::put('/{id}/reset-password', [UserManagementController::class, 'resetUserPassword']);
            Route::put('/{id}/role', [UserManagementController::class, 'updateRole']);
            Route::delete('/{id}', [UserManagementController::class, 'destroy']);
        });
    });

    // ========================================
    // [\ud83d\udd10 PENAGIHAN] Billing/Collection Routes (Role-Based Access)
    // ========================================
    // ✅ IMPORTANT: Specific routes MUST come before {id} wildcard!
    Route::prefix('penagihan')->group(function () {
        // 1️⃣ Specific routes (non-parameterized)
        Route::get('/statistics', [PenagihanController::class, 'statistics']);
        Route::get('/card-statistics', [PenagihanController::class, 'cardStatistics']);
        
        // 🎯 Priority System Routes (hanya super_admin dan admin)
        // ✅ MUST be before {id} to prevent {id} matching 'auto-prioritize'
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::post('/auto-prioritize', [PenagihanController::class, 'autoPrioritize']);
        });
        
        // 2️⃣ Export/Download routes (hanya super_admin dan admin)
        // ✅ MUST be before {id} to prevent {id} matching 'export' and 'template'
        Route::middleware('role:super_admin,admin')->group(function () {
            Route::get('/export', [PenagihanController::class, 'export']);
            Route::get('/template', [PenagihanController::class, 'downloadTemplate']);
        });
        
        // 3️⃣ Import route (super_admin, admin, dan viewer bisa import)
        // ✅ MUST be before {id} to prevent {id} matching 'import'
        Route::middleware('role:super_admin,admin,viewer')->group(function () {
            Route::post('/import', [PenagihanController::class, 'import']);
        });

        // 4️⃣ Generic routes with parameters (LAST)
        // Read access (semua user yang login bisa lihat)
        Route::get('/', [PenagihanController::class, 'index']);
        Route::post('/', [PenagihanController::class, 'store']);
        Route::middleware('role:super_admin,admin,viewer')->group(function () {
            Route::put('/{id}', [PenagihanController::class, 'update']);
            Route::delete('/{id}', [PenagihanController::class, 'destroy']);
            // 🎯 Set/Unset prioritas manual (super_admin dan admin)
            Route::put('/{id}/prioritize', [PenagihanController::class, 'setPrioritize']);
        });
        Route::get('/{id}', [PenagihanController::class, 'show']);
    });

    // ========================================
    // [\ud83d\udd10 ACTIVITY] Activity Log Routes (Super Admin & Admin)
    // ========================================
    Route::middleware('role:super_admin,admin')->prefix('aktivitas')->group(function () {
        Route::get('/', [AktivitasController::class, 'index']);
        Route::get('/{id}', [AktivitasController::class, 'show']);
    });
});
