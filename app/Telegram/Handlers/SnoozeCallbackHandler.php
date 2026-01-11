<?php

namespace App\Telegram\Handlers;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

class SnoozeCallbackHandler extends CallbackHandler
{
    protected string $match = '/^snooze:(.+):(\d+)$/';

    public function handle(): mixed
    {
        $callbackQuery = $this->update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;

        // Parse callback data: snooze:{alertId}:{minutes}
        $parts = explode(':', $data);
        if (count($parts) !== 3) {
            return $this->answerWithError('Invalid callback data');
        }

        $alertId = $parts[1];
        $minutes = (int) $parts[2];

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError('User not found');
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            return $this->answerWithError('Alert not found');
        }

        // Snooze the alert
        $alert->update([
            'snoozed_until' => now()->addMinutes($minutes),
        ]);

        Log::info('Alert snoozed via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'minutes' => $minutes,
        ]);

        $locale = $user->language ?? 'en';
        $message = $locale === 'ar'
            ? "⏰ تم تأجيل التنبيه لمدة {$minutes} دقيقة"
            : "⏰ Alert snoozed for {$minutes} min";

        $this->answerCallbackQuery([
            'text' => $message,
            'show_alert' => false,
        ]);

        return null;
    }

    private function answerWithError(string $message): null
    {
        $this->answerCallbackQuery([
            'text' => $message,
            'show_alert' => true,
        ]);

        return null;
    }
}
