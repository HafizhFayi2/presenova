<?php

use App\Http\Controllers\LegacyDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/index.php');
});

Route::get('/laravel-health', function () {
    return response()->json([
        'app' => config('app.name'),
        'status' => 'ok',
        'time' => now()->toIso8601String(),
    ]);
});

Route::prefix('dashboard')->group(function () {
    Route::get('/admin', [LegacyDashboardController::class, 'admin'])->name('dashboard.admin');
    Route::get('/guru', [LegacyDashboardController::class, 'guru'])->name('dashboard.guru');
    Route::get('/siswa', [LegacyDashboardController::class, 'siswa'])->name('dashboard.siswa');
});

$appUrlPathPrefix = trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

if ($appUrlPathPrefix !== '') {
    Route::prefix($appUrlPathPrefix)->group(function () {
        Route::get('/laravel-health', function () {
            return response()->json([
                'app' => config('app.name'),
                'status' => 'ok',
                'time' => now()->toIso8601String(),
            ]);
        });

        Route::prefix('dashboard')->group(function () {
            Route::get('/admin', [LegacyDashboardController::class, 'admin']);
            Route::get('/guru', [LegacyDashboardController::class, 'guru']);
            Route::get('/siswa', [LegacyDashboardController::class, 'siswa']);
        });
    });
}

Route::fallback(function () {
    return redirect('/404.php');
});
