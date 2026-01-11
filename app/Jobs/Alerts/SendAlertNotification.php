<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\AlertNotification;
use App\Models\FailedNotification;
use App\Models\UserAlertPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendAlertNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private Alert $alert,
        private AlertHistory $history,
        private array $context
    ) {}

    public function handle(): void
    {
        $user = $this->alert->user;
        $preferences = $user->getAlertPreferences();

        // Check rate limits
        if (! $this->checkRateLimits($preferences)) {
            Log::info('Alert notification rate limited', [
                'alert_id' => $this->alert->id,
                'user_id' => $user->id,
            ]);

            return;
        }

        // Determine priority
        $priority = $this->determinePriority();

        // Check quiet hours (skip for critical alerts)
        if ($priority !== 'critical' && $preferences->isInQuietHours()) {
            $this->scheduleForLater($preferences);

            return;
        }

        // Get channels to notify
        $channels = $this->getChannels($preferences, $priority);

        // Create and send notifications for each channel
        foreach ($channels as $channel) {
            $this->sendToChannel($channel, $priority, $preferences);
        }
    }

    private function checkRateLimits(UserAlertPreference $preferences): bool
    {
        $userId = $this->alert->user_id;

        // Check hourly limit
        $hourlyCount = AlertNotification::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourlyCount >= ($preferences->max_alerts_per_hour ?? 10)) {
            return false;
        }

        // Check daily limit
        $dailyCount = AlertNotification::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($dailyCount >= ($preferences->max_alerts_per_day ?? 25)) {
            return false;
        }

        return true;
    }

    private function determinePriority(): string
    {
        // Critical: trading halts, limit up/down, portfolio threshold breach
        $criticalTriggers = ['trading_halt', 'limit_up', 'limit_down', 'portfolio_threshold'];
        if (in_array($this->alert->trigger_type, $criticalTriggers)) {
            return 'critical';
        }

        // High: target price, breakout confirmed, earnings
        $highTriggers = ['target_price', 'breakout', 'earnings', 'dividend'];
        if (in_array($this->alert->trigger_type, $highTriggers)) {
            return 'high';
        }

        // Use alert's own priority if set
        if ($this->alert->priority) {
            return $this->alert->priority;
        }

        // Default to medium
        return 'medium';
    }

    private function getChannels(UserAlertPreference $preferences, string $priority): array
    {
        // Alert-specific delivery config takes precedence
        $alertChannels = $this->alert->delivery_config['channels'] ?? null;

        if ($alertChannels) {
            return $alertChannels;
        }

        // Priority-based channel selection
        $channels = match ($priority) {
            'critical' => ['telegram', 'push', 'sms', 'in_app'],
            'high' => ['telegram', 'push', 'in_app'],
            'medium' => ['telegram', 'in_app'],
            'low' => ['in_app'],
            default => ['in_app'],
        };

        // Filter by user preferences
        $userChannels = $preferences->getDefaultChannels();

        return array_intersect($channels, $userChannels);
    }

    private function sendToChannel(string $channel, string $priority, UserAlertPreference $preferences): void
    {
        $idempotencyKey = $this->generateIdempotencyKey($channel);

        // Check for duplicate
        if (AlertNotification::where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        // Build notification content
        $content = $this->buildNotificationContent();

        // Create notification record
        $notification = AlertNotification::create([
            'user_id' => $this->alert->user_id,
            'alert_id' => $this->alert->id,
            'alert_history_id' => $this->history->id,
            'idempotency_key' => $idempotencyKey,
            'type' => $this->alert->trigger_type,
            'channel' => $channel,
            'priority' => $priority,
            'title' => $content['title'],
            'title_ar' => $content['title_ar'],
            'body' => $content['body'],
            'body_ar' => $content['body_ar'],
            'data' => [
                'alert_id' => $this->alert->id,
                'asset_id' => $this->alert->asset_id,
                'trigger_value' => $this->history->trigger_value,
                'context' => $this->context,
            ],
            'status' => 'pending',
        ]);

        // Dispatch channel-specific job
        try {
            $this->dispatchToChannel($channel, $notification);
        } catch (\Exception $e) {
            $this->handleFailure($notification, $e);
        }
    }

    private function dispatchToChannel(string $channel, AlertNotification $notification): void
    {
        match ($channel) {
            'telegram' => $this->sendTelegram($notification),
            'push' => $this->sendPush($notification),
            'sms' => $this->sendSms($notification),
            'email' => $this->sendEmail($notification),
            'in_app' => $notification->markAsSent(), // In-app is just the record
            default => Log::warning("Unknown notification channel: {$channel}"),
        };
    }

    private function sendTelegram(AlertNotification $notification): void
    {
        $user = $notification->user;

        if (! $user->telegram_id) {
            $notification->markAsFailed('No Telegram ID configured');

            return;
        }

        // Queue Telegram message
        // This would integrate with a Telegram service/bot
        // For now, mark as sent
        $notification->markAsSent();

        Log::info('Telegram notification queued', [
            'notification_id' => $notification->id,
            'telegram_id' => $user->telegram_id,
        ]);
    }

    private function sendPush(AlertNotification $notification): void
    {
        // Would integrate with Firebase/APNS
        $notification->markAsSent();

        Log::info('Push notification queued', [
            'notification_id' => $notification->id,
        ]);
    }

    private function sendSms(AlertNotification $notification): void
    {
        $user = $notification->user;

        if (! $user->phone || ! $user->hasVerifiedPhone()) {
            $notification->markAsFailed('No verified phone number');

            return;
        }

        // Would integrate with SMS provider
        $notification->markAsSent();

        Log::info('SMS notification queued', [
            'notification_id' => $notification->id,
        ]);
    }

    private function sendEmail(AlertNotification $notification): void
    {
        $user = $notification->user;

        if (! $user->email) {
            $notification->markAsFailed('No email address');

            return;
        }

        // Would queue email notification
        $notification->markAsSent();

        Log::info('Email notification queued', [
            'notification_id' => $notification->id,
        ]);
    }

    private function handleFailure(AlertNotification $notification, \Exception $e): void
    {
        $notification->markAsFailed($e->getMessage());
        $notification->incrementRetry();

        // Move to dead letter queue after max retries
        if ($notification->retry_count >= $this->tries) {
            FailedNotification::create([
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'channel' => $notification->channel,
                'payload' => $notification->data,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
        }

        Log::error('Notification failed', [
            'notification_id' => $notification->id,
            'channel' => $notification->channel,
            'error' => $e->getMessage(),
        ]);
    }

    private function scheduleForLater(UserAlertPreference $preferences): void
    {
        // Schedule notification for after quiet hours end
        $quietEnd = $preferences->quiet_hours_end;
        $timezone = $preferences->timezone ?? 'Africa/Cairo';

        $scheduledAt = now()
            ->setTimezone($timezone)
            ->setTimeFromTimeString($quietEnd)
            ->setTimezone('UTC');

        // If the time has passed today, schedule for tomorrow
        if ($scheduledAt->isPast()) {
            $scheduledAt->addDay();
        }

        // Re-dispatch with delay
        self::dispatch($this->alert, $this->history, $this->context)
            ->delay($scheduledAt);

        Log::info('Notification scheduled for after quiet hours', [
            'alert_id' => $this->alert->id,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    private function buildNotificationContent(): array
    {
        $asset = $this->alert->asset;
        $assetName = $asset?->name ?? 'Unknown';
        $assetNameAr = $asset?->name_ar ?? $assetName;
        $triggerValue = $this->history->trigger_value;

        $title = $this->buildTitle($assetName, $triggerValue);
        $titleAr = $this->buildTitleAr($assetNameAr, $triggerValue);
        $body = $this->buildBody($assetName, $triggerValue);
        $bodyAr = $this->buildBodyAr($assetNameAr, $triggerValue);

        return [
            'title' => $title,
            'title_ar' => $titleAr,
            'body' => $body,
            'body_ar' => $bodyAr,
        ];
    }

    private function buildTitle(string $assetName, mixed $triggerValue): string
    {
        return match ($this->alert->trigger_type) {
            'target_price' => "{$assetName} reached target price",
            'breakout' => "{$assetName} breakout detected",
            'daily_change' => "{$assetName} moved significantly",
            '52week' => "{$assetName} hit 52-week level",
            'signal' => "New signal for {$assetName}",
            'anomaly' => "Anomaly detected in {$assetName}",
            'pattern' => "Pattern detected in {$assetName}",
            'recommendation' => "Recommendation update for {$assetName}",
            default => "Alert for {$assetName}",
        };
    }

    private function buildTitleAr(string $assetName, mixed $triggerValue): string
    {
        return match ($this->alert->trigger_type) {
            'target_price' => "{$assetName} وصل للسعر المستهدف",
            'breakout' => "اختراق في {$assetName}",
            'daily_change' => "تحرك كبير في {$assetName}",
            '52week' => "{$assetName} وصل لمستوى ٥٢ أسبوع",
            'signal' => "إشارة جديدة لـ {$assetName}",
            'anomaly' => "شذوذ في {$assetName}",
            'pattern' => "نمط في {$assetName}",
            'recommendation' => "تحديث توصية لـ {$assetName}",
            default => "تنبيه لـ {$assetName}",
        };
    }

    private function buildBody(string $assetName, mixed $triggerValue): string
    {
        $params = $this->alert->parameters;
        $context = $this->context;

        return match ($this->alert->trigger_type) {
            'target_price' => sprintf(
                '%s has reached %.2f EGP (Target: %.2f EGP)',
                $assetName,
                $triggerValue,
                $params['target_price'] ?? 0
            ),
            'breakout' => sprintf(
                '%s broke %s %.2f EGP (Current: %.2f EGP)',
                $assetName,
                $params['direction'] ?? 'above',
                $params['level'] ?? 0,
                $triggerValue
            ),
            'daily_change' => sprintf(
                '%s changed %.2f%% today (Current: %.2f EGP)',
                $assetName,
                $triggerValue,
                $context['current_price'] ?? 0
            ),
            default => sprintf('%s alert triggered at %.2f', $assetName, $triggerValue),
        };
    }

    private function buildBodyAr(string $assetName, mixed $triggerValue): string
    {
        $params = $this->alert->parameters;
        $context = $this->context;

        return match ($this->alert->trigger_type) {
            'target_price' => sprintf(
                '%s وصل إلى %.2f ج.م (المستهدف: %.2f ج.م)',
                $assetName,
                $triggerValue,
                $params['target_price'] ?? 0
            ),
            'breakout' => sprintf(
                '%s اخترق %.2f ج.م (الحالي: %.2f ج.م)',
                $assetName,
                $params['level'] ?? 0,
                $triggerValue
            ),
            'daily_change' => sprintf(
                '%s تغير %.2f%% اليوم (الحالي: %.2f ج.م)',
                $assetName,
                $triggerValue,
                $context['current_price'] ?? 0
            ),
            default => sprintf('تنبيه %s عند %.2f', $assetName, $triggerValue),
        };
    }

    private function generateIdempotencyKey(string $channel): string
    {
        return Str::uuid()->toString().'-'.$channel.'-'.$this->history->id;
    }
}
