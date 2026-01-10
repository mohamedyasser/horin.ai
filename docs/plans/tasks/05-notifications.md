# Task 05: Notification System

**Priority:** P1
**Effort:** 3 days
**Dependencies:** Task 02

---

## Objective

Build the multi-channel notification delivery system supporting Telegram, In-App, Push, and Email channels with rate limiting, escalation, and localization.

---

## Checklist

- [ ] Create `SendAlertNotification` job
- [ ] Create `AlertTriggeredNotification` class
- [ ] Create Telegram channel driver
- [ ] Create In-App notification storage
- [ ] Create WebSocket broadcasting (Reverb)
- [ ] Implement rate limiting
- [ ] Implement quiet hours
- [ ] Implement escalation logic
- [ ] Create notification templates (AR/EN)
- [ ] Create digest job
- [ ] Add failed notification handling

---

## Notification Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Alert Triggered                                   │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│              SendAlertNotification Job                               │
│                                                                      │
│  1. Create notification record (idempotency)                        │
│  2. Check rate limits                                               │
│  3. Check quiet hours                                               │
│  4. Determine channels from user preferences                        │
│  5. Format message (AR/EN)                                          │
│  6. Dispatch to channels                                            │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
         ┌────────────────────────┼────────────────────────┐
         ▼                        ▼                        ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│    Telegram     │    │     In-App      │    │      Push       │
│                 │    │                 │    │   (Firebase)    │
│  Bot API Send   │    │ DB + WebSocket  │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                        │                        │
         ▼                        ▼                        ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    Delivery Tracking                                 │
│           (sent_at, delivered_at, read_at, failed_reason)           │
└─────────────────────────────────────────────────────────────────────┘
```

---

## SendAlertNotification Job

```bash
php artisan make:job Alerts/SendAlertNotification
```

```php
<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\AlertNotification;
use App\Models\FailedNotification;
use App\Models\UserAlertPreference;
use App\Notifications\AlertTriggeredNotification;
use App\Services\AlertMetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SendAlertNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300]; // 10s, 1m, 5m

    public function __construct(
        public Alert $alert,
        public AlertHistory $history,
        public array $triggerData
    ) {
        $this->queue = 'notifications';
    }

    public function handle(AlertMetricsService $metrics): void
    {
        $user = $this->alert->user;
        $preferences = $user->alertPreferences ?? UserAlertPreference::getDefaults();

        // 1. Create idempotent notification record
        $notification = $this->createNotificationRecord();

        if ($notification->status !== 'pending') {
            // Already processed (duplicate job)
            return;
        }

        // 2. Check rate limits
        if (!$this->checkRateLimits($user->id, $preferences)) {
            $notification->update([
                'status' => 'rate_limited',
                'failed_reason' => 'Rate limit exceeded',
            ]);
            Log::info('Notification rate limited', [
                'user_id' => $user->id,
                'alert_id' => $this->alert->id,
            ]);
            return;
        }

        // 3. Check quiet hours
        if ($this->isQuietHours($preferences) && $this->alert->priority !== 'critical') {
            // Schedule for after quiet hours
            $notification->update([
                'scheduled_at' => $this->getQuietHoursEnd($preferences),
            ]);
            SendAlertNotification::dispatch($this->alert, $this->history, $this->triggerData)
                ->delay($this->getQuietHoursEnd($preferences));
            return;
        }

        // 4. Determine channels
        $channels = $this->getChannelsForPriority($preferences, $this->alert->priority);

        // 5. Send to each channel
        $sentChannels = [];
        foreach ($channels as $channel) {
            try {
                $this->sendToChannel($channel, $notification);
                $sentChannels[] = $channel;

                $metrics->recordNotificationSent($notification);

            } catch (\Throwable $e) {
                Log::error("Failed to send notification via {$channel}", [
                    'error' => $e->getMessage(),
                    'notification_id' => $notification->id,
                ]);

                $metrics->recordNotificationFailed($notification, $e->getMessage());

                // Continue to next channel
            }
        }

        // 6. Update notification status
        if (count($sentChannels) > 0) {
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
                'channel' => $sentChannels[0], // Primary channel
            ]);

            // Update alert history
            $this->history->update(['notification_sent' => true]);

            // Broadcast to WebSocket
            $this->broadcastToUser($notification);
        }
    }

    private function createNotificationRecord(): AlertNotification
    {
        $idempotencyKey = "{$this->alert->id}:{$this->history->id}";

        return AlertNotification::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $this->alert->user_id,
                'alert_id' => $this->alert->id,
                'alert_history_id' => $this->history->id,
                'type' => 'alert.triggered',
                'channel' => 'pending',
                'priority' => $this->alert->priority,
                'title' => $this->buildTitle('en'),
                'title_ar' => $this->buildTitle('ar'),
                'body' => $this->buildBody('en'),
                'body_ar' => $this->buildBody('ar'),
                'data' => $this->buildDeepLinkData(),
                'status' => 'pending',
            ]
        );
    }

    private function checkRateLimits(string $userId, UserAlertPreference $preferences): bool
    {
        // Per hour limit
        $hourKey = "alert_notifications:{$userId}:hourly";
        if (RateLimiter::tooManyAttempts($hourKey, $preferences->max_alerts_per_hour)) {
            return false;
        }
        RateLimiter::hit($hourKey, 3600);

        // Per day limit
        $dayKey = "alert_notifications:{$userId}:daily";
        if (RateLimiter::tooManyAttempts($dayKey, $preferences->max_alerts_per_day)) {
            return false;
        }
        RateLimiter::hit($dayKey, 86400);

        return true;
    }

    private function isQuietHours(UserAlertPreference $preferences): bool
    {
        if (!$preferences->quiet_hours_start || !$preferences->quiet_hours_end) {
            return false;
        }

        $timezone = new \DateTimeZone($preferences->timezone ?? 'Africa/Cairo');
        $now = now()->setTimezone($timezone);
        $time = $now->format('H:i');

        $start = $preferences->quiet_hours_start->format('H:i');
        $end = $preferences->quiet_hours_end->format('H:i');

        // Handle overnight quiet hours (e.g., 23:00 - 07:00)
        if ($start > $end) {
            return $time >= $start || $time <= $end;
        }

        return $time >= $start && $time <= $end;
    }

    private function getQuietHoursEnd(UserAlertPreference $preferences): \DateTime
    {
        $timezone = new \DateTimeZone($preferences->timezone ?? 'Africa/Cairo');
        $end = clone now()->setTimezone($timezone);

        list($hour, $minute) = explode(':', $preferences->quiet_hours_end->format('H:i'));
        $end->setTime((int) $hour, (int) $minute);

        // If end time is earlier than now, it's tomorrow
        if ($end < now()) {
            $end->addDay();
        }

        return $end;
    }

    private function getChannelsForPriority(UserAlertPreference $preferences, string $priority): array
    {
        $defaultChannels = $preferences->default_channels ?? ['telegram', 'in_app'];
        $deliveryConfig = $this->alert->delivery_config;

        // Alert-specific override
        if ($deliveryConfig && isset($deliveryConfig['channels'])) {
            return $deliveryConfig['channels'];
        }

        // Priority-based filtering
        $priorityChannels = [];
        foreach ($defaultChannels as $channel) {
            $channelConfig = $preferences->channel_config[$channel] ?? [];
            $forPriorities = $channelConfig['for_priorities'] ?? ['critical', 'high', 'medium', 'low'];

            if (in_array($priority, $forPriorities)) {
                $priorityChannels[] = $channel;
            }
        }

        return $priorityChannels;
    }

    private function sendToChannel(string $channel, AlertNotification $notification): void
    {
        $user = $this->alert->user;

        match ($channel) {
            'telegram' => $this->sendTelegram($user, $notification),
            'in_app' => $this->sendInApp($notification),
            'push' => $this->sendPush($user, $notification),
            'email' => $this->sendEmail($user, $notification),
            default => throw new \InvalidArgumentException("Unknown channel: {$channel}"),
        };
    }

    private function sendTelegram($user, AlertNotification $notification): void
    {
        if (!$user->telegram_id) {
            throw new \Exception('User has no Telegram ID');
        }

        $message = $this->formatTelegramMessage($notification);

        // Use existing Telegram bot service
        app(\App\Services\TelegramBotService::class)->sendMessage(
            $user->telegram_id,
            $message,
            [
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        ['text' => '📈 View Stock', 'url' => route('assets.show', $this->alert->asset)],
                        ['text' => '⚙️ Manage Alert', 'url' => route('alerts.edit', $this->alert)],
                    ]],
                ]),
            ]
        );
    }

    private function sendInApp(AlertNotification $notification): void
    {
        // In-app notifications are stored in the database (already done via $notification)
        // The WebSocket broadcast happens separately
    }

    private function sendPush($user, AlertNotification $notification): void
    {
        if (!$user->push_token) {
            throw new \Exception('User has no push token');
        }

        // Firebase Cloud Messaging
        $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $user->push_token)
            ->withNotification([
                'title' => $user->locale === 'ar' ? $notification->title_ar : $notification->title,
                'body' => $user->locale === 'ar' ? $notification->body_ar : $notification->body,
            ])
            ->withData($notification->data);

        app('firebase.messaging')->send($message);
    }

    private function sendEmail($user, AlertNotification $notification): void
    {
        $user->notify(new AlertTriggeredNotification($this->alert, $this->history, $this->triggerData));
    }

    private function broadcastToUser(AlertNotification $notification): void
    {
        broadcast(new \App\Events\AlertTriggered($notification))->toOthers();
    }

    private function buildTitle(string $locale): string
    {
        $titles = [
            'target_price' => [
                'en' => '🎯 Target Price Reached',
                'ar' => '🎯 تم الوصول للسعر المستهدف',
            ],
            'breakout' => [
                'en' => '📈 Breakout Alert',
                'ar' => '📈 تنبيه اختراق',
            ],
            'signal' => [
                'en' => '📊 Signal Alert',
                'ar' => '📊 تنبيه إشارة',
            ],
            'anomaly' => [
                'en' => '⚠️ Anomaly Detected',
                'ar' => '⚠️ تم اكتشاف شذوذ',
            ],
            'pattern' => [
                'en' => '📐 Pattern Alert',
                'ar' => '📐 تنبيه نمط',
            ],
            'recommendation' => [
                'en' => '💡 Recommendation Update',
                'ar' => '💡 تحديث توصية',
            ],
            'prediction' => [
                'en' => '🤖 AI Prediction Alert',
                'ar' => '🤖 تنبيه تنبؤ ذكاء اصطناعي',
            ],
        ];

        $triggerType = $this->alert->trigger_type;
        $type = $this->alert->type;

        return $titles[$triggerType][$locale]
            ?? $titles[$type][$locale]
            ?? ($locale === 'ar' ? '🔔 تنبيه' : '🔔 Alert');
    }

    private function buildBody(string $locale): string
    {
        $asset = $this->alert->asset;
        $assetName = $locale === 'ar' ? $asset->name_ar : $asset->name;
        $triggerValue = $this->triggerData['trigger_value'] ?? null;
        $currentPrice = $this->triggerData['current_price'] ?? null;

        if ($locale === 'ar') {
            return "السهم {$asset->symbol} - {$assetName}\n" .
                   ($triggerValue ? "القيمة: {$triggerValue} ج.م\n" : '') .
                   ($currentPrice ? "السعر الحالي: {$currentPrice} ج.م" : '');
        }

        return "{$asset->symbol} - {$assetName}\n" .
               ($triggerValue ? "Value: {$triggerValue} EGP\n" : '') .
               ($currentPrice ? "Current Price: {$currentPrice} EGP" : '');
    }

    private function buildDeepLinkData(): array
    {
        return [
            'type' => 'alert_triggered',
            'alert_id' => $this->alert->id,
            'asset_id' => $this->alert->asset_id,
            'history_id' => $this->history->id,
            'screen' => 'asset_detail',
            'params' => [
                'symbol' => $this->alert->asset->symbol,
            ],
        ];
    }

    private function formatTelegramMessage(AlertNotification $notification): string
    {
        $user = $this->alert->user;
        $asset = $this->alert->asset;
        $locale = $user->locale ?? 'en';

        $title = $locale === 'ar' ? $notification->title_ar : $notification->title;
        $assetName = $locale === 'ar' ? $asset->name_ar : $asset->name;
        $currentPrice = $this->triggerData['current_price'] ?? 'N/A';
        $targetPrice = $this->alert->parameters['target_price'] ?? null;
        $changePercent = $this->triggerData['change_percent'] ?? null;

        $divider = '━━━━━━━━━━━━━━━━━━';
        $time = now()->setTimezone('Africa/Cairo')->format('h:i A · M d, Y');

        $message = "*{$title}*\n{$divider}\n\n";
        $message .= "*{$asset->symbol}* - {$assetName}\n\n";
        $message .= ($locale === 'ar' ? "السعر الحالي: *" : "Current Price: *") . "{$currentPrice} " .
                    ($locale === 'ar' ? 'ج.م*' : 'EGP*') . "\n";

        if ($targetPrice) {
            $message .= ($locale === 'ar' ? "المستهدف: " : "Target: ") . "{$targetPrice} " .
                        ($locale === 'ar' ? 'ج.م' : 'EGP') . "\n";
        }

        if ($changePercent) {
            $sign = $changePercent >= 0 ? '+' : '';
            $message .= ($locale === 'ar' ? "التغير: " : "Change: ") . "{$sign}{$changePercent}% " .
                        ($locale === 'ar' ? 'اليوم' : 'today') . "\n";
        }

        $message .= "\n🕐 {$time}\n\n{$divider}";

        return $message;
    }

    public function failed(\Throwable $e): void
    {
        FailedNotification::create([
            'notification_id' => $this->history->id,
            'alert_id' => $this->alert->id,
            'user_id' => $this->alert->user_id,
            'error' => $e->getMessage(),
            'payload' => [
                'trigger_data' => $this->triggerData,
                'alert' => $this->alert->toArray(),
            ],
            'failed_at' => now(),
        ]);

        Log::error('Alert notification failed permanently', [
            'alert_id' => $this->alert->id,
            'user_id' => $this->alert->user_id,
            'error' => $e->getMessage(),
        ]);
    }
}
```

---

## AlertTriggered Event

```bash
php artisan make:event AlertTriggered
```

```php
<?php

namespace App\Events;

use App\Models\AlertNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AlertNotification $notification
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->notification->user_id}.alerts"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'alert.triggered';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'priority' => $this->notification->priority,
            'title' => $this->notification->title,
            'title_ar' => $this->notification->title_ar,
            'body' => $this->notification->body,
            'body_ar' => $this->notification->body_ar,
            'data' => $this->notification->data,
            'created_at' => $this->notification->created_at->toISOString(),
        ];
    }
}
```

---

## Broadcasting Channel Authorization

Add to `routes/channels.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}.alerts', function (User $user, string $userId) {
    return $user->id === $userId;
});
```

---

## AlertTriggeredNotification (Email)

```bash
php artisan make:notification AlertTriggeredNotification
```

```php
<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\AlertHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertTriggeredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Alert $alert,
        public AlertHistory $history,
        public array $triggerData
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'en';
        $asset = $this->alert->asset;
        $assetName = $locale === 'ar' ? $asset->name_ar : $asset->name;

        $subject = $locale === 'ar'
            ? "🔔 تنبيه: {$asset->symbol}"
            : "🔔 Alert: {$asset->symbol}";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($locale === 'ar' ? 'مرحباً!' : 'Hello!')
            ->line($locale === 'ar'
                ? "تم تفعيل التنبيه الخاص بك للسهم {$asset->symbol} - {$assetName}"
                : "Your alert for {$asset->symbol} - {$assetName} has been triggered.")
            ->line($locale === 'ar'
                ? "السعر الحالي: {$this->triggerData['current_price']} ج.م"
                : "Current Price: {$this->triggerData['current_price']} EGP")
            ->action(
                $locale === 'ar' ? 'عرض السهم' : 'View Stock',
                route('assets.show', $asset)
            )
            ->line($locale === 'ar'
                ? 'شكراً لاستخدامك كيرا!'
                : 'Thank you for using Kira!');

        return $message;
    }
}
```

---

## Escalation Job

```bash
php artisan make:job Alerts/ProcessEscalation
```

```php
<?php

namespace App\Jobs\Alerts;

use App\Models\AlertHistory;
use App\Models\AlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEscalation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Find unacknowledged critical/high alerts that need escalation
        $unacknowledged = AlertHistory::whereNull('acknowledged_at')
            ->where('triggered_at', '<', now()->subMinutes(5))
            ->whereHas('alert', function ($q) {
                $q->whereIn('priority', ['critical', 'high'])
                  ->whereNotNull('escalation_config');
            })
            ->with(['alert.user', 'alert'])
            ->get();

        foreach ($unacknowledged as $history) {
            $this->escalateIfNeeded($history);
        }
    }

    private function escalateIfNeeded(AlertHistory $history): void
    {
        $alert = $history->alert;
        $config = $alert->escalation_config;

        if (!$config || !($config['enabled'] ?? false)) {
            return;
        }

        $levels = $config['levels'] ?? [];
        $currentLevel = $history->escalation_level;
        $maxLevel = $config['max_escalations'] ?? 2;

        if ($currentLevel >= $maxLevel) {
            return; // Already at max escalation
        }

        $nextLevel = $currentLevel + 1;
        $levelConfig = $levels[$nextLevel] ?? null;

        if (!$levelConfig) {
            return;
        }

        // Check if enough time has passed since last notification
        $lastNotification = AlertNotification::where('alert_history_id', $history->id)
            ->latest()
            ->first();

        $delayMinutes = $levelConfig['delay_minutes'] ?? 5;
        $condition = $levelConfig['condition'] ?? 'not_acknowledged';

        if ($condition === 'not_acknowledged') {
            if ($history->acknowledged_at !== null) {
                return; // Already acknowledged
            }
        }

        if ($lastNotification && $lastNotification->sent_at->addMinutes($delayMinutes) > now()) {
            return; // Not enough time has passed
        }

        // Create escalation notification
        $notification = AlertNotification::create([
            'idempotency_key' => "{$alert->id}:{$history->id}:escalation:{$nextLevel}",
            'user_id' => $alert->user_id,
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
            'type' => 'alert.escalated',
            'channel' => $levelConfig['channel'],
            'priority' => 'critical',
            'title' => '⚠️ ESCALATION: ' . $alert->asset->symbol,
            'title_ar' => '⚠️ تصعيد: ' . $alert->asset->symbol,
            'body' => "Your {$alert->trigger_type} alert was not acknowledged. This is escalation level {$nextLevel}.",
            'body_ar' => "لم يتم الإقرار بتنبيهك. هذا المستوى {$nextLevel} من التصعيد.",
            'data' => ['escalation_level' => $nextLevel],
            'status' => 'pending',
            'escalation_level' => $nextLevel,
        ]);

        // Send via escalation channel
        $this->sendEscalation($notification, $levelConfig['channel']);

        // Update history
        $history->update([
            'escalation_level' => $nextLevel,
        ]);

        $notification->update([
            'escalated_at' => now(),
        ]);

        Log::info('Alert escalated', [
            'alert_id' => $alert->id,
            'history_id' => $history->id,
            'level' => $nextLevel,
            'channel' => $levelConfig['channel'],
        ]);
    }

    private function sendEscalation(AlertNotification $notification, string $channel): void
    {
        $user = $notification->user;

        match ($channel) {
            'sms' => $this->sendSms($user, $notification),
            'telegram' => $this->sendTelegram($user, $notification),
            'push' => $this->sendPush($user, $notification),
            default => Log::warning("Unknown escalation channel: {$channel}"),
        };
    }

    private function sendSms($user, AlertNotification $notification): void
    {
        // Integrate with SMS provider (Twilio, MessageBird, etc.)
        // For now, just log
        Log::info('Would send SMS escalation', [
            'user_id' => $user->id,
            'phone' => $user->phone,
            'message' => $notification->body,
        ]);
    }

    private function sendTelegram($user, AlertNotification $notification): void
    {
        if (!$user->telegram_id) {
            return;
        }

        app(\App\Services\TelegramBotService::class)->sendMessage(
            $user->telegram_id,
            "⚠️ *ESCALATION*\n\n{$notification->body}",
            ['parse_mode' => 'Markdown']
        );
    }

    private function sendPush($user, AlertNotification $notification): void
    {
        if (!$user->push_token) {
            return;
        }

        $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $user->push_token)
            ->withNotification([
                'title' => $notification->title,
                'body' => $notification->body,
            ])
            ->withAndroidConfig(
                \Kreait\Firebase\Messaging\AndroidConfig::new()
                    ->withPriority('high')
                    ->withNotification(
                        \Kreait\Firebase\Messaging\AndroidNotification::create()
                            ->withChannelId('alerts_critical')
                    )
            );

        app('firebase.messaging')->send($message);
    }
}
```

---

## Digest Notification Job

```bash
php artisan make:job Alerts/GenerateDigest
```

```php
<?php

namespace App\Jobs\Alerts;

use App\Models\AlertHistory;
use App\Models\User;
use App\Models\UserAlertPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $digestType = 'daily' // 'daily' or 'weekly'
    ) {}

    public function handle(): void
    {
        $users = User::whereHas('alertPreferences', function ($q) {
            $q->where('digest_enabled', true);
        })->get();

        foreach ($users as $user) {
            $this->generateUserDigest($user);
        }
    }

    private function generateUserDigest(User $user): void
    {
        $period = $this->digestType === 'daily'
            ? now()->subDay()
            : now()->subWeek();

        $history = AlertHistory::where('user_id', $user->id)
            ->where('triggered_at', '>=', $period)
            ->with(['alert.asset'])
            ->orderBy('triggered_at', 'desc')
            ->get();

        if ($history->isEmpty()) {
            return; // No alerts to digest
        }

        // Group by asset
        $byAsset = $history->groupBy(fn($h) => $h->alert->asset_id);

        // Build digest content
        $locale = $user->locale ?? 'en';
        $content = $this->buildDigestContent($byAsset, $locale);

        // Send digest
        $this->sendDigest($user, $content);

        Log::info('Digest generated', [
            'user_id' => $user->id,
            'type' => $this->digestType,
            'alerts_count' => $history->count(),
        ]);
    }

    private function buildDigestContent($byAsset, string $locale): string
    {
        $lines = [];

        $title = $this->digestType === 'daily'
            ? ($locale === 'ar' ? '📊 ملخص التنبيهات اليومي' : '📊 Daily Alert Summary')
            : ($locale === 'ar' ? '📊 ملخص التنبيهات الأسبوعي' : '📊 Weekly Alert Summary');

        $lines[] = "*{$title}*";
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        foreach ($byAsset as $assetId => $alerts) {
            $asset = $alerts->first()->alert->asset;
            $assetName = $locale === 'ar' ? $asset->name_ar : $asset->name;

            $lines[] = '';
            $lines[] = "*{$asset->symbol}* - {$assetName}";
            $lines[] = ($locale === 'ar' ? 'عدد التنبيهات: ' : 'Alerts: ') . $alerts->count();

            foreach ($alerts->take(3) as $alert) {
                $time = $alert->triggered_at->format('M d H:i');
                $type = $alert->alert->trigger_type;
                $lines[] = "  • {$type} @ {$time}";
            }

            if ($alerts->count() > 3) {
                $remaining = $alerts->count() - 3;
                $lines[] = ($locale === 'ar' ? "  ... و{$remaining} المزيد" : "  ... and {$remaining} more");
            }
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        return implode("\n", $lines);
    }

    private function sendDigest(User $user, string $content): void
    {
        if ($user->telegram_id) {
            app(\App\Services\TelegramBotService::class)->sendMessage(
                $user->telegram_id,
                $content,
                ['parse_mode' => 'Markdown']
            );
        }
    }
}
```

---

## Schedule Configuration

Add to `routes/console.php`:

```php
use App\Jobs\Alerts\ProcessEscalation;
use App\Jobs\Alerts\GenerateDigest;
use Illuminate\Support\Facades\Schedule;

// Process escalations every 5 minutes
Schedule::job(new ProcessEscalation())
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Daily digest at 8 PM Cairo time
Schedule::job(new GenerateDigest('daily'))
    ->dailyAt('20:00')
    ->timezone('Africa/Cairo');

// Weekly digest on Friday at 3 PM Cairo time
Schedule::job(new GenerateDigest('weekly'))
    ->weeklyOn(5, '15:00')
    ->timezone('Africa/Cairo');
```

---

## Notification Templates

### Telegram Template Examples

**Target Price Hit:**
```
🎯 *Target Price Reached*
━━━━━━━━━━━━━━━━━━

*COMI* - Commercial International Bank

Current Price: *52.50 EGP*
Target: 52.00 EGP
Change: +4.2% today

🕐 10:45 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
```

**Signal Alert:**
```
📊 *Signal Alert: RSI Oversold*
━━━━━━━━━━━━━━━━━━

*EFIH* - EFG Hermes

RSI: 28.5 (Oversold)
Indicator: RSI (14)
Signal: Strong Buy Opportunity

Current Price: *15.20 EGP*
Confidence: 85%

🕐 11:30 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
```

**Pattern Alert:**
```
📐 *Pattern Detected: Double Bottom*
━━━━━━━━━━━━━━━━━━

*TMGH* - Talaat Moustafa Group

Pattern: Double Bottom (Bullish)
Confidence: 78%
Target: 32.50 EGP (+12%)

Support: 28.00 EGP
Resistance: 30.50 EGP

🕐 2:00 PM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
```

---

## Rate Limiting Configuration

Add to `config/alerts.php`:

```php
<?php

return [
    'rate_limits' => [
        'per_user_per_hour' => env('ALERT_RATE_LIMIT_HOURLY', 10),
        'per_user_per_day' => env('ALERT_RATE_LIMIT_DAILY', 50),
        'per_alert_per_day' => env('ALERT_RATE_LIMIT_PER_ALERT', 3),

        // Channel-specific limits
        'telegram_per_hour' => env('TELEGRAM_RATE_LIMIT_HOURLY', 20),
        'push_per_hour' => env('PUSH_RATE_LIMIT_HOURLY', 30),
        'email_per_hour' => env('EMAIL_RATE_LIMIT_HOURLY', 10),
    ],

    'escalation' => [
        'default_levels' => [
            ['level' => 0, 'channel' => 'push', 'delay_minutes' => 0],
            ['level' => 1, 'channel' => 'telegram', 'delay_minutes' => 5, 'condition' => 'not_acknowledged'],
            ['level' => 2, 'channel' => 'sms', 'delay_minutes' => 15, 'condition' => 'not_acknowledged'],
        ],
        'max_escalations' => 2,
    ],

    'quiet_hours' => [
        'default_start' => '23:00',
        'default_end' => '07:00',
        'allow_critical' => true,
    ],
];
```

---

## Testing

### Unit Test: Rate Limiting

```php
<?php

namespace Tests\Unit\Alerts;

use App\Jobs\Alerts\SendAlertNotification;
use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\User;
use App\Models\UserAlertPreference;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    /** @test */
    public function it_respects_hourly_rate_limit(): void
    {
        $user = User::factory()->create();
        UserAlertPreference::factory()->create([
            'user_id' => $user->id,
            'max_alerts_per_hour' => 2,
        ]);

        $alert = Alert::factory()->create(['user_id' => $user->id]);

        // Send first 2 notifications (should succeed)
        for ($i = 0; $i < 2; $i++) {
            $history = AlertHistory::factory()->create(['alert_id' => $alert->id]);
            $job = new SendAlertNotification($alert, $history, []);
            $job->handle(app(\App\Services\AlertMetricsService::class));
        }

        // Third should be rate limited
        $history = AlertHistory::factory()->create(['alert_id' => $alert->id]);
        $job = new SendAlertNotification($alert, $history, []);
        $job->handle(app(\App\Services\AlertMetricsService::class));

        $this->assertDatabaseHas('alert_notifications', [
            'alert_history_id' => $history->id,
            'status' => 'rate_limited',
        ]);
    }
}
```

### Feature Test: Telegram Delivery

```php
<?php

namespace Tests\Feature\Alerts;

use App\Jobs\Alerts\SendAlertNotification;
use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\Asset;
use App\Models\User;
use App\Services\TelegramBotService;
use Mockery;
use Tests\TestCase;

class TelegramDeliveryTest extends TestCase
{
    /** @test */
    public function it_sends_telegram_notification(): void
    {
        $telegramMock = Mockery::mock(TelegramBotService::class);
        $telegramMock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $message, $options) {
                return $chatId === '123456789' &&
                       str_contains($message, 'Target Price');
            });

        $this->app->instance(TelegramBotService::class, $telegramMock);

        $user = User::factory()->create(['telegram_id' => '123456789']);
        $asset = Asset::factory()->create(['symbol' => 'COMI']);
        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'trigger_type' => 'target_price',
        ]);
        $history = AlertHistory::factory()->create(['alert_id' => $alert->id]);

        $job = new SendAlertNotification($alert, $history, [
            'current_price' => 52.50,
            'trigger_value' => 52.00,
        ]);

        $job->handle(app(\App\Services\AlertMetricsService::class));

        $this->assertDatabaseHas('alert_notifications', [
            'alert_id' => $alert->id,
            'status' => 'sent',
        ]);
    }
}
```

---

## Verification

After implementation, verify:

```bash
# Test notification sending
php artisan tinker
>>> $alert = Alert::first();
>>> $history = AlertHistory::factory()->create(['alert_id' => $alert->id]);
>>> SendAlertNotification::dispatch($alert, $history, ['current_price' => 50.00]);

# Check queue
php artisan queue:work notifications --once

# Check notifications table
>>> AlertNotification::latest()->first();
```

---

## Next Task

Proceed to [Task 06: API & Controllers](./06-api-controllers.md)
