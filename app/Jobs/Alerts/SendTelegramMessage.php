<?php

namespace App\Jobs\Alerts;

use App\Models\AlertNotification;
use App\Services\TelegramBotService;
use App\Services\TelegramMessageBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private AlertNotification $notification
    ) {}

    public function handle(
        TelegramBotService $telegram,
        TelegramMessageBuilder $messageBuilder
    ): void {
        $user = $this->notification->user;

        if (! $user || ! $user->telegram_id) {
            $this->notification->markAsFailed('No Telegram ID');

            return;
        }

        $locale = $user->language ?? 'en';

        try {
            $messageData = $messageBuilder->buildAlertMessage($this->notification, $locale);

            $result = $telegram->sendMessageWithKeyboard(
                $user->telegram_id,
                $messageData['text'],
                $messageData['keyboard']
            );

            $this->notification->update([
                'status' => 'sent',
                'sent_at' => now(),
                'data' => array_merge($this->notification->data ?? [], [
                    'telegram_message_id' => $result->message_id ?? null,
                ]),
            ]);

            Log::info('Telegram alert sent', [
                'notification_id' => $this->notification->id,
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
            ]);

        } catch (\Exception $e) {
            $this->handleFailure($e);
        }
    }

    private function handleFailure(\Exception $e): void
    {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();

        // Handle specific Telegram errors
        if (str_contains($errorMessage, 'chat not found') ||
            str_contains($errorMessage, 'bot was blocked') ||
            $errorCode === 403) {
            // User blocked the bot or chat doesn't exist
            $this->notification->markAsFailed('User blocked bot or invalid chat');
            $this->notification->user?->update(['telegram_id' => null]);

            return;
        }

        if ($errorCode === 429) {
            // Rate limited - retry after delay
            $retryAfter = $this->extractRetryAfter($errorMessage) ?? 60;
            $this->release($retryAfter);

            return;
        }

        // General failure
        $this->notification->markAsFailed($errorMessage);
        $this->notification->incrementRetry();

        Log::error('Telegram send failed', [
            'notification_id' => $this->notification->id,
            'error' => $errorMessage,
            'code' => $errorCode,
        ]);

        if ($this->attempts() >= $this->tries) {
            $this->fail($e);
        }
    }

    private function extractRetryAfter(string $message): ?int
    {
        if (preg_match('/retry after (\d+)/i', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Telegram message job failed permanently', [
            'notification_id' => $this->notification->id,
            'error' => $e->getMessage(),
        ]);
    }
}
