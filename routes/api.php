<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PenagihanController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;

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

// Public routes (tanpa autentikasi)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

// Protected routes (perlu login)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile routes (semua user bisa akses)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/change-password', [ProfileController::class, 'changePassword']);

    // Penagihan routes dengan role-based access
    Route::prefix('penagihan')->group(function () {
        // Read access (semua user yang login bisa lihat)
        Route::get('/', [PenagihanController::class, 'index']);
        Route::get('/statistics', [PenagihanController::class, 'statistics']);
        Route::get('/{id}', [PenagihanController::class, 'show']);

        // Write access untuk CRUD (hanya super_admin dan viewer, TIDAK read_only)
        Route::middleware('role:super_admin,viewer')->group(function () {
            Route::post('/', [PenagihanController::class, 'store']);
            Route::put('/{id}', [PenagihanController::class, 'update']);
            Route::delete('/{id}', [PenagihanController::class, 'destroy']);
            Route::post('/import', [PenagihanController::class, 'import']);
        });
        
        // Export/Template (semua bisa akses)
        Route::get('/export', [PenagihanController::class, 'export']);
        Route::get('/template', [PenagihanController::class, 'downloadTemplate']);
    });

    // User Management routes (hanya super_admin)
    Route::middleware('role:super_admin')->prefix('users')->group(function () {
        Route::get('/', [UserManagementController::class, 'index']);
        Route::put('/{id}/reset-password', [UserManagementController::class, 'resetUserPassword']);
        Route::put('/{id}/role', [UserManagementController::class, 'updateRole']);
    });
});
