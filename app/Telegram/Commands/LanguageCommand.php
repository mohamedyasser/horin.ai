<?php

namespace App\Telegram\Commands;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;

class LanguageCommand extends CommandHandler
{
    protected static function aliases(): array
    {
        return ['/language', '/lang'];
    }

    protected static function description(?string $locale = null): string
    {
        return 'Change your language preference';
    }

    public function handle(): mixed
    {
        $chatId = $this->update->message->chat->id;
        $telegramId = (string) $this->update->message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $this->sendLoginPrompt($chatId);

            return null;
        }

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => '🌐 Select your language / اختر لغتك:',
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => '🇬🇧 English',
                        'callback_data' => 'lang:en',
                    ],
                    [
                        'text' => '🇸🇦 العربية',
                        'callback_data' => 'lang:ar',
                    ],
                ]],
            ],
        ]);

        return null;
    }

    private function sendLoginPrompt(int $chatId): void
    {
        $locale = $this->update->message->from->language_code ?? 'en';

        $text = $locale === 'ar'
            ? '👋 مرحباً! افتح التطبيق للبدء.'
            : '👋 Hi! Open the app to get started.';

        $buttonText = $locale === 'ar' ? '🚀 فتح حورين' : '🚀 Open Horin';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $buttonText,
                        'web_app' => ['url' => config('app.url')],
                    ],
                ]],
            ],
        ]);
    }
}
