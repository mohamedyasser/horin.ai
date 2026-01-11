<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

class LanguageCallbackHandler extends CallbackHandler
{
    protected string $match = '/^lang:(en|ar)$/';

    public function handle(): mixed
    {
        $callbackQuery = $this->update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;

        // Parse callback data: lang:{locale}
        $parts = explode(':', $data);
        if (count($parts) !== 2) {
            return $this->answerWithError('Invalid callback data');
        }

        $newLocale = $parts[1];

        if (! in_array($newLocale, ['en', 'ar'])) {
            return $this->answerWithError('Invalid language');
        }

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError('User not found');
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

        $this->answerCallbackQuery([
            'text' => $message,
            'show_alert' => false,
        ]);

        // Update the original message to reflect the change
        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        $confirmText = $newLocale === 'ar'
            ? '✅ تم تغيير اللغة إلى العربية بنجاح.'
            : '✅ Language successfully changed to English.';

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $confirmText,
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
