<?php

namespace App\Telegram\Commands;

use App\Models\Alert;
use App\Models\User;
use App\Telegram\Services\DefaultKeyboardBuilder;
use WeStacks\TeleBot\Foundation\CommandHandler;

class AlertsCommand extends CommandHandler
{
    protected static function aliases(): array
    {
        return ['/alerts'];
    }

    protected static function description(?string $locale = null): string
    {
        return 'Manage your alerts';
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
        $this->sendAlertsMenu($chatId, $user, $locale);

        return null;
    }

    /**
     * Send the main alerts menu.
     */
    public function sendAlertsMenu(int $chatId, User $user, string $locale): void
    {
        $activeCount = Alert::where('user_id', $user->id)->active()->count();
        $triggeredToday = Alert::where('user_id', $user->id)
            ->whereDate('last_triggered_at', today())
            ->count();

        // Build stats line
        $statsLine = $locale === 'ar'
            ? "نشط: {$activeCount} | تم تفعيلها اليوم: {$triggeredToday}"
            : "Active: {$activeCount} | Triggered Today: {$triggeredToday}";

        $text = $locale === 'ar'
            ? "📋 *تنبيهاتك*\n\n{$statsLine}\n\nاختر من القائمة أدناه:"
            : "📋 *Your Alerts*\n\n{$statsLine}\n\nChoose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
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
