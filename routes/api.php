<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\Api\NewsInteractionController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Notifications API
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Quick alert count for mobile/widgets
    Route::get('alerts/active-count', [AlertController::class, 'activeCount']);

    // News interactions
    Route::post('news/{assetNew}/bookmark', [NewsInteractionController::class, 'toggleBookmark']);
    Route::post('news/{assetNew}/rate', [NewsInteractionController::class, 'rate']);

    // News comments
    Route::get('news/{assetNew}/comments', [NewsInteractionController::class, 'getComments']);
    Route::post('news/{assetNew}/comments', [NewsInteractionController::class, 'storeComment']);
    Route::delete('news/{assetNew}/comments/{comment}', [NewsInteractionController::class, 'deleteComment']);
});
