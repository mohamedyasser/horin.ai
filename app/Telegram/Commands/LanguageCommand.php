<?php

namespace App\Telegram\Commands;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class LanguageCommand extends CommandHandler
{
    protected static function command(): string
    {
        return 'language';
    }

    protected static function aliases(): array
    {
        return ['lang'];
    }

    protected static function description(): string
    {
        return 'Change your language preference';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $chatId = $update->message->chat->id;
        $telegramId = (string) $update->message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Please login through the Horin app first.',
            ]);

            return null;
        }

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => "🌐 Select your language / اختر لغتك:",
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
