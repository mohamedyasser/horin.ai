<?php

namespace App\Telegram\Handlers;

use App\Models\AlertHistory;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

class AcknowledgeCallbackHandler extends CallbackHandler
{
    protected string $match = '/^ack:(.+)$/';

    public function handle(): mixed
    {
        $callbackQuery = $this->update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;

        // Parse callback data: ack:{historyId}
        $parts = explode(':', $data);
        if (count($parts) !== 2) {
            return $this->answerWithError('Invalid callback data');
        }

        $historyId = $parts[1];

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError('User not found');
        }

        $history = AlertHistory::where('id', $historyId)
            ->whereHas('alert', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (! $history) {
            return $this->answerWithError('Alert not found');
        }

        // Acknowledge the alert
        $history->update([
            'acknowledged_at' => now(),
        ]);

        Log::info('Alert acknowledged via Telegram', [
            'history_id' => $history->id,
            'user_id' => $user->id,
        ]);

        $locale = $user->language ?? 'en';
        $message = $locale === 'ar' ? '✅ تم التأكيد' : '✅ Acknowledged';

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
