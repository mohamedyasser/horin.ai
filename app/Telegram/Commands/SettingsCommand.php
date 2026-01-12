<?php

namespace App\Telegram\Commands;

use App\Models\User;
use App\Telegram\Services\DefaultKeyboardBuilder;
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
            ? "⚙️ *الإعدادات*\n\nاختر ما تريد تغييره من القائمة أدناه:"
            : "⚙️ *Settings*\n\nChoose what you want to change from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
        ]);
    }

    private function sendLoginPrompt(int $chatId): void
    {
        $langCode = $this->update->message->from->language_code ?? 'en';
        $locale = str_starts_with($langCode, 'ar') ? 'ar' : 'en';

        $text = $locale === 'ar'
            ? "👋 مرحباً! يرجى التحقق من رقم هاتفك للبدء.\n\nاضغط الزر أدناه لمشاركة رقم هاتفك."
            : "👋 Hi! Please verify your phone number to get started.\n\nTap the button below to share your phone number.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::phoneVerificationKeyboard($locale),
        ]);
    }
}
