<?php

namespace App\Telegram\Handlers\Settings;

use App\Models\User;
use App\Telegram\Services\SettingsKeyboardBuilder;
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
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'ما الذي تريد تغييره؟'
            : 'What would you like to change?';

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildProfileMenu($user, $locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function promptNameChange(int $chatId, User $user, string $locale): mixed
    {
        $builder = new SettingsKeyboardBuilder;

        // Set awaiting input state
        $user->update(['telegram_awaiting_input' => 'name']);

        $text = $locale === 'ar'
            ? "✏️ أدخل اسمك الجديد:\n\nاكتب اسمك في الرسالة التالية."
            : "✏️ Enter your new name:\n\nType your name in your next message.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => $builder->buildCancelButton('set:profile', $locale),
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
