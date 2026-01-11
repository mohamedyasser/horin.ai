<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpsAlertService
{
    private ?string $slackWebhook;

    private ?string $opsTelegramChatId;

    public function __construct()
    {
        $this->slackWebhook = config('alerts.ops.slack_webhook');
        $this->opsTelegramChatId = config('alerts.ops.telegram_chat_id');
    }

    public function alert(string $title, string $message, string $severity = 'warning'): void
    {
        $emoji = match ($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '🔔',
        };

        // Log first
        $logLevel = $severity === 'critical' ? 'critical' : ($severity === 'warning' ? 'warning' : 'info');
        Log::$logLevel("[OPS ALERT] {$title}", ['message' => $message]);

        // Send to Slack
        if ($this->slackWebhook) {
            $this->sendSlack("{$emoji} *{$title}*\n{$message}");
        }

        // Send to Telegram ops channel
        if ($this->opsTelegramChatId) {
            $this->sendTelegram("{$emoji} *{$title}*\n\n{$message}");
        }
    }

    public function alertIfThreshold(string $metric, float $value, float $threshold, string $title): void
    {
        if ($value > $threshold) {
            $this->alert(
                $title,
                "Current: {$value}, Threshold: {$threshold}",
                'warning'
            );
        }
    }

    public function alertQueueDepth(string $queue, int $depth): void
    {
        $maxDepth = config('alerts.health.max_queue_depth', 1000);
        $warningThreshold = $maxDepth / 2;

        if ($depth > $maxDepth) {
            $this->alert(
                "Queue Depth Critical: {$queue}",
                "Current depth: {$depth}, Threshold: {$maxDepth}",
                'critical'
            );
        } elseif ($depth > $warningThreshold) {
            $this->alert(
                "Queue Depth Warning: {$queue}",
                "Current depth: {$depth}, Warning threshold: {$warningThreshold}",
                'warning'
            );
        }
    }

    public function alertFailureRate(float $rate): void
    {
        $maxRate = config('alerts.health.max_failure_rate', 0.05);

        if ($rate > $maxRate * 2) {
            $this->alert(
                'Notification Failure Rate Critical',
                'Current rate: '.number_format($rate * 100, 1).'%',
                'critical'
            );
        } elseif ($rate > $maxRate) {
            $this->alert(
                'Notification Failure Rate Elevated',
                'Current rate: '.number_format($rate * 100, 1).'%',
                'warning'
            );
        }
    }

    private function sendSlack(string $message): void
    {
        try {
            Http::post($this->slackWebhook, [
                'text' => $message,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack ops alert', ['error' => $e->getMessage()]);
        }
    }

    private function sendTelegram(string $message): void
    {
        try {
            if (app()->bound(TelegramBotService::class)) {
                app(TelegramBotService::class)->sendMessage(
                    $this->opsTelegramChatId,
                    $message,
                    ['parse_mode' => 'Markdown']
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram ops alert', ['error' => $e->getMessage()]);
        }
    }
}
