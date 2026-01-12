<?php

namespace App\Telegram\Commands;

use App\Models\Alert;
use App\Models\User;
use App\Telegram\Services\AlertKeyboardBuilder;
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
        $builder = new AlertKeyboardBuilder;

        $activeCount = Alert::where('user_id', $user->id)->active()->count();
        $triggeredToday = Alert::where('user_id', $user->id)
            ->whereDate('last_triggered_at', today())
            ->count();

        // Build stats line
        $statsLine = $locale === 'ar'
            ? "نشط: {$activeCount} | تم تفعيلها اليوم: {$triggeredToday}"
            : "Active: {$activeCount} | Triggered Today: {$triggeredToday}";

        $text = $locale === 'ar'
            ? "📋 *تنبيهاتك*\n\n{$statsLine}"
            : "📋 *Your Alerts*\n\n{$statsLine}";

        $keyboard = $builder->buildMainMenu($user, $locale);

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
