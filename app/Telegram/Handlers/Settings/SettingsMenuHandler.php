<?php

namespace App\Telegram\Handlers\Settings;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Settings main menu navigation.
 *
 * Callback patterns:
 * - set:menu - Return to main settings menu
 * - set:language - Show language selection
 */
class SettingsMenuHandler extends CallbackHandler
{
    protected string $match = '/^set:(menu|language)$/';

    public function handle(): mixed
    {
        $callbackQuery = $this->update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError('User not found. Please /start first.');
        }

        $parts = explode(':', $data);
        $action = $parts[1];
        $locale = $user->language ?? 'en';

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        return match ($action) {
            'menu' => $this->showMainMenu($chatId, $messageId, $locale),
            'language' => $this->showLanguageMenu($chatId, $messageId, $locale),
            default => $this->answerWithError('Invalid action'),
        };
    }

    private function showMainMenu(int $chatId, int $messageId, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? 'ما الذي تريد تغييره؟'
            : 'What would you like to change?';

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

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function showLanguageMenu(int $chatId, int $messageId, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? 'اختر لغتك:'
            : 'Select your language:';

        $keyboard = [
            [
                [
                    'text' => $locale === 'en' ? '✓ 🇬🇧 English' : '🇬🇧 English',
                    'callback_data' => 'lang:en',
                ],
                [
                    'text' => $locale === 'ar' ? '✓ 🇸🇦 العربية' : '🇸🇦 العربية',
                    'callback_data' => 'lang:ar',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                    'callback_data' => 'set:menu',
                ],
            ],
        ];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

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
