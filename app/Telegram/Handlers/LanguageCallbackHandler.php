<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class LanguageCallbackHandler extends CallbackHandler
{
    protected static function match(Update $update): bool
    {
        $data = $update->callback_query->data ?? '';

        return str_starts_with($data, 'lang:');
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $callbackQuery = $update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;

        // Parse callback data: lang:{locale}
        $parts = explode(':', $data);
        if (count($parts) !== 2) {
            return $this->answerWithError($bot, $callbackQuery->id, 'Invalid callback data');
        }

        $newLocale = $parts[1];

        if (! in_array($newLocale, ['en', 'ar'])) {
            return $this->answerWithError($bot, $callbackQuery->id, 'Invalid language');
        }

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError($bot, $callbackQuery->id, 'User not found');
        }

        // Update language preference
        $user->update([
            'language' => $newLocale,
        ]);

        Log::info('Language changed via Telegram', [
            'user_id' => $user->id,
            'new_locale' => $newLocale,
        ]);

        $message = $newLocale === 'ar'
            ? '✅ تم تغيير اللغة إلى العربية'
            : '✅ Language changed to English';

        $bot->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->id,
            'text' => $message,
            'show_alert' => false,
        ]);

        // Update the original message to reflect the change
        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        $confirmText = $newLocale === 'ar'
            ? '✅ تم تغيير اللغة إلى العربية بنجاح.'
            : '✅ Language successfully changed to English.';

        $bot->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $confirmText,
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
