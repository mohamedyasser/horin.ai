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
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Please login through the Horin app first.',
            ]);

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
}
