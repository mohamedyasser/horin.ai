<?php

namespace App\Telegram\Handlers;

use App\Models\AlertHistory;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class AcknowledgeCallbackHandler extends CallbackHandler
{
    protected static function match(Update $update): bool
    {
        $data = $update->callback_query->data ?? '';

        return str_starts_with($data, 'ack:');
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $callbackQuery = $update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;

        // Parse callback data: ack:{historyId}
        $parts = explode(':', $data);
        if (count($parts) !== 2) {
            return $this->answerWithError($bot, $callbackQuery->id, 'Invalid callback data');
        }

        $historyId = $parts[1];

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError($bot, $callbackQuery->id, 'User not found');
        }

        $history = AlertHistory::where('id', $historyId)
            ->whereHas('alert', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (! $history) {
            return $this->answerWithError($bot, $callbackQuery->id, 'Alert not found');
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

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->id,
            'text' => $message,
            'show_alert' => false,
        ]);

        return null;
    }

    private function answerWithError(TeleBot $bot, string $callbackId, string $message): null
    {
        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackId,
            'text' => $message,
            'show_alert' => true,
        ]);

        return null;
    }
}
