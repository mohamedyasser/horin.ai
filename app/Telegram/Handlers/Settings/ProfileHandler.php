<?php

namespace App\Telegram\Handlers\Settings;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Profile settings.
 *
 * Callback patterns:
 * - set:profile - Show profile settings
 * - set:profile:name - Prompt for name change
 */
class ProfileHandler extends CallbackHandler
{
    protected string $match = '/^set:profile/';

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
        $action = $parts[2] ?? null;
        $locale = $user->language ?? 'en';

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        if ($action === 'name') {
            return $this->promptNameChange($chatId, $user, $locale);
        }

        return $this->showProfileSettings($chatId, $messageId, $user, $locale);
    }

    private function showProfileSettings(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $name = $user->name ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
        $langDisplay = $locale === 'ar' ? 'العربية' : 'English';

        // Simple question: What would you like to change?
        $text = $locale === 'ar'
            ? 'ما الذي تريد تغييره؟'
            : 'What would you like to change?';

        $keyboard = [
            [[
                'text' => $locale === 'ar' ? "📛 الاسم: {$name}" : "📛 Name: {$name}",
                'callback_data' => 'set:profile:name',
            ]],
            [[
                'text' => $locale === 'ar' ? "🌐 اللغة: {$langDisplay}" : "🌐 Language: {$langDisplay}",
                'callback_data' => 'set:language',
            ]],
            [[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'set:menu',
            ]],
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

    private function promptNameChange(int $chatId, User $user, string $locale): mixed
    {
        // Set awaiting input state
        $user->update(['telegram_awaiting_input' => 'name']);

        $text = $locale === 'ar'
            ? "✏️ أدخل اسمك الجديد:\n\nاكتب اسمك في الرسالة التالية."
            : "✏️ Enter your new name:\n\nType your name in your next message.";

        $cancelText = $locale === 'ar' ? '⬅️ إلغاء' : '⬅️ Cancel';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $cancelText,
                        'callback_data' => 'set:profile',
                    ],
                ]],
            ],
        ]);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? 'أدخل اسمك أدناه' : 'Type your name below',
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
