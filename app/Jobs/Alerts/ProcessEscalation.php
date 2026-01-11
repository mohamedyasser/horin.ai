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
            ->with(['alert.user', 'alert.asset'])
            ->get();

        $escalatedCount = 0;

        foreach ($unacknowledged as $history) {
            if ($this->escalateIfNeeded($history)) {
                $escalatedCount++;
            }
        }

        if ($escalatedCount > 0) {
            Log::info('Processed escalations', [
                'checked' => $unacknowledged->count(),
                'escalated' => $escalatedCount,
            ]);
        }
    }

    private function escalateIfNeeded(AlertHistory $history): bool
    {
        $alert = $history->alert;
        $config = $alert->escalation_config;

        if (! $config || ! ($config['enabled'] ?? false)) {
            return false;
        }

        $levels = $config['levels'] ?? [];
        $currentLevel = $history->escalation_level ?? 0;
        $maxLevel = $config['max_escalations'] ?? 2;

        if ($currentLevel >= $maxLevel) {
            return false; // Already at max escalation
        }

        $nextLevel = $currentLevel + 1;
        $levelConfig = $levels[$nextLevel] ?? null;

        if (! $levelConfig) {
            return false;
        }

        // Check if enough time has passed since last notification
        $lastNotification = AlertNotification::where('alert_history_id', $history->id)
            ->latest()
            ->first();

        $delayMinutes = $levelConfig['delay_minutes'] ?? 5;

        if ($lastNotification && $lastNotification->sent_at?->addMinutes($delayMinutes)->isFuture()) {
            return false; // Not enough time has passed
        }

        // Create escalation notification
        $notification = AlertNotification::create([
            'idempotency_key' => "{$alert->id}:{$history->id}:escalation:{$nextLevel}",
            'user_id' => $alert->user_id,
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
            'type' => 'alert.escalated',
            'channel' => $levelConfig['channel'] ?? 'telegram',
            'priority' => 'critical',
            'title' => $this->buildEscalationTitle($alert, $nextLevel),
            'title_ar' => $this->buildEscalationTitleAr($alert, $nextLevel),
            'body' => $this->buildEscalationBody($alert, $nextLevel),
            'body_ar' => $this->buildEscalationBodyAr($alert, $nextLevel),
            'data' => ['escalation_level' => $nextLevel],
            'status' => 'pending',
            'escalation_level' => $nextLevel,
        ]);

        // Send via escalation channel
        $this->sendEscalation($notification, $levelConfig['channel'] ?? 'telegram');

        // Update history
        $history->update([
            'escalation_level' => $nextLevel,
        ]);

        $notification->update([
            'escalated_at' => now(),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Log::info('Alert escalated', [
            'alert_id' => $alert->id,
            'history_id' => $history->id,
            'level' => $nextLevel,
            'channel' => $levelConfig['channel'] ?? 'telegram',
        ]);

        return true;
    }

    private function buildEscalationTitle($alert, int $level): string
    {
        $symbol = $alert->asset?->symbol ?? 'Unknown';

        return "⚠️ ESCALATION (Level {$level}): {$symbol}";
    }

    private function buildEscalationTitleAr($alert, int $level): string
    {
        $symbol = $alert->asset?->symbol ?? 'غير معروف';

        return "⚠️ تصعيد (المستوى {$level}): {$symbol}";
    }

    private function buildEscalationBody($alert, int $level): string
    {
        $triggerType = $alert->trigger_type;

        return "Your {$triggerType} alert was not acknowledged. This is escalation level {$level}. Please check immediately.";
    }

    private function buildEscalationBodyAr($alert, int $level): string
    {
        return "لم يتم الإقرار بالتنبيه الخاص بك. هذا المستوى {$level} من التصعيد. يرجى المراجعة فوراً.";
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
        if (! $user->phone || ! $user->hasVerifiedPhone()) {
            Log::warning('Cannot send SMS escalation - no verified phone', [
                'user_id' => $user->id,
            ]);

            return;
        }

        // TODO: Integrate with SMS provider (Twilio, MessageBird, etc.)
        Log::info('SMS escalation queued', [
            'user_id' => $user->id,
            'phone' => $user->phone,
        ]);
    }

    private function sendTelegram($user, AlertNotification $notification): void
    {
        if (! $user->telegram_id) {
            Log::warning('Cannot send Telegram escalation - no telegram_id', [
                'user_id' => $user->id,
            ]);

            return;
        }

        // TODO: Use TelegramBotService when available
        Log::info('Telegram escalation queued', [
            'user_id' => $user->id,
            'telegram_id' => $user->telegram_id,
        ]);
    }

    private function sendPush($user, AlertNotification $notification): void
    {
        // TODO: Integrate with Firebase Cloud Messaging
        Log::info('Push escalation queued', [
            'user_id' => $user->id,
        ]);
    }
}
