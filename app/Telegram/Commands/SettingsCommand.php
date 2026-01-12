<?php

namespace App\Telegram\Commands;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;

class SettingsCommand extends CommandHandler
{
    protected static function aliases(): array
    {
        return ['/settings'];
    }

    protected static function description(?string $locale = null): string
    {
        return 'View your notification settings';
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

        $locale = $user->language ?? 'en';

        $this->sendSettingsMenu($chatId, $locale);

        return null;
    }

    /**
     * Send the settings main menu.
     */
    public function sendSettingsMenu(int $chatId, string $locale): void
    {
        $text = $locale === 'ar'
            ? "⚙️ *الإعدادات*\n\nاختر فئة للتعديل:"
            : "⚙️ *Settings*\n\nChoose a category to modify:";

        $keyboard = [
            [
                [
                    'text' => $locale === 'ar' ? '👤 الملف الشخصي' : '👤 Profile',
                    'callback_data' => 'set:profile',
                ],
                [
                    'text' => $locale === 'ar' ? '📊 التداول' : '📊 Trading',
                    'callback_data' => 'set:trading',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '🌍 الأسواق' : '🌍 Markets',
                    'callback_data' => 'set:markets',
                ],
                [
                    'text' => $locale === 'ar' ? '🔔 التنبيهات' : '🔔 Alerts',
                    'callback_data' => 'set:alerts',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '🌐 اللغة' : '🌐 Language',
                    'callback_data' => 'set:language',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '📱 فتح التطبيق' : '📱 Open App',
                    'web_app' => ['url' => config('app.url').'/settings/profile'],
                ],
            ],
        ];

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);
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
