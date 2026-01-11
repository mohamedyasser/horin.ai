<?php

use App\Http\Controllers\Admin\AlertStatisticsController;
use App\Http\Controllers\PrometheusMetricsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin & Monitoring Routes
|--------------------------------------------------------------------------
|
| These routes are for admin dashboards and system monitoring.
|
*/

// Prometheus metrics endpoint (protected by bearer token)
Route::get('/metrics', PrometheusMetricsController::class)
    ->middleware('auth.metrics')
    ->name('metrics');

// Admin routes (require authentication and admin role)
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Alert system statistics
    Route::get('alerts/statistics', [AlertStatisticsController::class, 'index'])
        ->name('alerts.statistics');

    Route::get('alerts/health', [AlertStatisticsController::class, 'health'])
        ->name('alerts.health');
});
