<?php

use App\Jobs\Alerts\GenerateDigest;
use App\Jobs\Alerts\ProcessEscalation;
use App\Jobs\Alerts\ProcessPriceAlerts;
use App\Jobs\CleanupAlertHistory;
use App\Jobs\CleanupBacktestResults;
use App\Jobs\CleanupNotifications;
use App\Services\AlertCacheService;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

/*
|--------------------------------------------------------------------------
| Alert Processing Schedules
|--------------------------------------------------------------------------
|
| These schedules handle the alert system processing for price alerts,
| intelligence alerts (via Redis listener), and cache management.
|
*/

// Every minute: process price alerts during market hours
Schedule::job(new ProcessPriceAlerts)
    ->everyMinute()
    ->withoutOverlapping()
    ->name('process-price-alerts');

// Refresh alert cache every minute for fast lookups
Schedule::call(function () {
    app(AlertCacheService::class)->cacheActiveAlerts();
})
    ->everyMinute()
    ->name('refresh-alert-cache')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Market Event Schedules (Egypt Market - Africa/Cairo timezone)
|--------------------------------------------------------------------------
|
| Egypt Stock Exchange hours: 10:00 AM - 2:30 PM (Sun-Thu)
| Weekend: Friday and Saturday
|
*/

// Market open (10:00 AM Cairo): process gap alerts
// TODO: Create ProcessScheduledAlerts job for market open/close events
// Schedule::job(new ProcessScheduledAlerts('market_open'))
//     ->dailyAt('10:00')
//     ->timezone('Africa/Cairo')
//     ->weekdays()
//     ->when(fn () => !in_array(now('Africa/Cairo')->dayOfWeek, [5, 6])) // Skip Fri-Sat
//     ->name('market-open-alerts');

// Market close (14:30 PM Cairo): process end-of-day alerts
// Schedule::job(new ProcessScheduledAlerts('market_close'))
//     ->dailyAt('14:30')
//     ->timezone('Africa/Cairo')
//     ->weekdays()
//     ->when(fn () => !in_array(now('Africa/Cairo')->dayOfWeek, [5, 6])) // Skip Fri-Sat
//     ->name('market-close-alerts');

/*
|--------------------------------------------------------------------------
| Notification & Escalation Schedules
|--------------------------------------------------------------------------
*/

// Process notification escalations every 5 minutes
Schedule::job(new ProcessEscalation)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('process-escalations');

/*
|--------------------------------------------------------------------------
| Digest Schedules
|--------------------------------------------------------------------------
*/

// Daily digest at 8 PM Cairo time
Schedule::job(new GenerateDigest('daily'))
    ->dailyAt('20:00')
    ->timezone('Africa/Cairo')
    ->name('daily-digest');

// Weekly digest on Thursday at 3 PM (before Egypt weekend)
Schedule::job(new GenerateDigest('weekly'))
    ->weeklyOn(4, '15:00')
    ->timezone('Africa/Cairo')
    ->name('weekly-digest');

/*
|--------------------------------------------------------------------------
| Maintenance Schedules
|--------------------------------------------------------------------------
*/

// Clean up expired alerts
Schedule::call(function () {
    \App\Models\Alert::where('expires_at', '<', now())
        ->where('status', 'active')
        ->update(['status' => 'expired']);
})
    ->hourly()
    ->name('expire-alerts');

// Clean up old failed notifications (keep 30 days)
Schedule::call(function () {
    \App\Models\FailedNotification::where('created_at', '<', now()->subDays(30))
        ->delete();
})
    ->daily()
    ->at('04:00')
    ->timezone('Africa/Cairo')
    ->name('cleanup-failed-notifications');

// Clean up alert history (keep configurable days, default 90)
Schedule::job(new CleanupAlertHistory(
    retentionDays: (int) config('alerts.retention.alert_history_days', 90)
))
    ->dailyAt('03:00')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->name('cleanup-alert-history');

// Clean up old notifications (keep configurable days, default 30)
Schedule::job(new CleanupNotifications(
    retentionDays: (int) config('alerts.retention.notifications_days', 30)
))
    ->dailyAt('03:15')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->name('cleanup-notifications');

// Clean up old backtest results (keep configurable days, default 7)
Schedule::job(new CleanupBacktestResults(
    retentionDays: (int) config('alerts.retention.backtest_results_days', 7)
))
    ->dailyAt('03:30')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->name('cleanup-backtest-results');

/*
|--------------------------------------------------------------------------
| Health Check Schedules
|--------------------------------------------------------------------------
*/

// Health check every 5 minutes during market hours, alert if issues
Schedule::command('alerts:health-check --alert')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->name('alert-health-check');
