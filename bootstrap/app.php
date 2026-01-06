<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Enable CORS handling for API requests (fixes browser CORS blocks from frontend dev server)
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Run daily reminder setiap hari jam 08:00 pagi
        $schedule->command('notifikasi:daily-reminder')
                 ->dailyAt('08:00')
                 ->timezone('Asia/Jakarta')
                 ->withoutOverlapping()
                 ->onSuccess(function () {
                     \Illuminate\Support\Facades\Log::info('[SCHEDULER] Daily reminder berhasil dijalankan');
                 })
                 ->onFailure(function () {
                     \Illuminate\Support\Facades\Log::error('[SCHEDULER] Daily reminder gagal dijalankan');
                 });
        
        // Run priority recalculation setiap hari jam 09:00 pagi
        $schedule->command('priority:recalculate')
                 ->dailyAt('09:00')
                 ->timezone('Asia/Jakarta')
                 ->withoutOverlapping()
                 ->onSuccess(function () {
                     \Illuminate\Support\Facades\Log::info('[SCHEDULER] Priority recalculation berhasil dijalankan');
                 })
                 ->onFailure(function () {
                     \Illuminate\Support\Facades\Log::error('[SCHEDULER] Priority recalculation gagal dijalankan');
                 });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
