<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AlertHistoryController;
use App\Http\Controllers\AlertPreferencesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Alert Routes
|--------------------------------------------------------------------------
|
| These routes handle all alert-related functionality including CRUD
| operations, history viewing, acknowledgment, and preferences.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    // Asset search for alert creation
    Route::get('alerts/search-assets', [AlertController::class, 'searchAssets'])->name('alerts.search-assets');

    // Alerts CRUD
    Route::resource('alerts', AlertController::class);

    // Additional alert actions
    Route::post('alerts/{alert}/snooze', [AlertController::class, 'snooze'])->name('alerts.snooze');
    Route::delete('alerts/{alert}/snooze', [AlertController::class, 'unsnooze'])->name('alerts.unsnooze');
    Route::post('alerts/{alert}/backtest', [AlertController::class, 'backtest'])->name('alerts.backtest');
    Route::get('alerts/{alert}/backtest/results', [AlertController::class, 'backtestResults'])->name('alerts.backtest.results');
    Route::post('alerts/{alert}/duplicate', [AlertController::class, 'duplicate'])->name('alerts.duplicate');

    // Alert History
    Route::get('alerts-history', [AlertHistoryController::class, 'index'])->name('alerts.history');
    Route::post('alerts-history/{history}/acknowledge', [AlertHistoryController::class, 'acknowledge'])->name('alerts.history.acknowledge');

    // Alert Preferences
    Route::get('settings/alerts', [AlertPreferencesController::class, 'edit'])->name('settings.alerts');
    Route::patch('settings/alerts', [AlertPreferencesController::class, 'update'])->name('settings.alerts.update');
});
