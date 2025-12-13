<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PenagihanController;
use App\Http\Controllers\AuthController;

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

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Penagihan routes (temporarily public - remove auth middleware)
Route::prefix('penagihan')->group(function () {
    Route::get('/', [PenagihanController::class, 'index']);
    Route::post('/', [PenagihanController::class, 'store']);
    Route::get('/statistics', [PenagihanController::class, 'statistics']);
    Route::post('/import', [PenagihanController::class, 'import']); // ✅ FIXED: Changed from GET to POST
    Route::get('/export', [PenagihanController::class, 'export']); // ✅ ADDED: Export route
    Route::get('/template', [PenagihanController::class, 'downloadTemplate']);
    Route::get('/{id}', [PenagihanController::class, 'show']);
    Route::put('/{id}', [PenagihanController::class, 'update']);
    Route::delete('/{id}', [PenagihanController::class, 'destroy']);
});

// Protected routes (auth disabled temporarily)
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
});
