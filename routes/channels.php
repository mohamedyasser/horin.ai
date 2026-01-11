<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/*
|--------------------------------------------------------------------------
| User Alert Channels
|--------------------------------------------------------------------------
|
| Private channel for real-time alert notifications. Each user can only
| subscribe to their own alert channel.
|
*/
Broadcast::channel('user.{userId}.alerts', function (User $user, string $userId) {
    return $user->id === $userId;
});

/*
|--------------------------------------------------------------------------
| User Notification Channel
|--------------------------------------------------------------------------
|
| General notification channel for all user notifications (not just alerts).
|
*/
Broadcast::channel('user.{userId}.notifications', function (User $user, string $userId) {
    return $user->id === $userId;
});
