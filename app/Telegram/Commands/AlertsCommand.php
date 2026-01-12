<?php

namespace App\Telegram\Commands;

use App\Models\Alert;
use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;

class AlertsCommand extends CommandHandler
{
    protected static function aliases(): array
    {
        return ['/alerts'];
    }

    protected static function description(?string $locale = null): string
    {
        return 'View your active alerts';
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

        $alerts = Alert::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('asset')
            ->limit(10)
            ->get();

        if ($alerts->isEmpty()) {
            $text = $locale === 'ar'
                ? '📭 لا توجد تنبيهات نشطة.\n\nاستخدم التطبيق لإنشاء تنبيهات جديدة.'
                : "📭 No active alerts.\n\nUse the app to create new alerts.";

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => [
                    'inline_keyboard' => [[
                        [
                            'text' => $locale === 'ar' ? '➕ إنشاء تنبيه' : '➕ Create Alert',
                            'web_app' => ['url' => config('app.url').'/dashboard'],
                        ],
                    ]],
                ],
            ]);

            return null;
        }

        $lines = [];
        $lines[] = $locale === 'ar' ? '📋 *تنبيهاتك النشطة:*' : '📋 *Your Active Alerts:*';
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        foreach ($alerts as $alert) {
            $symbol = $alert->asset?->symbol ?? 'N/A';
            $type = $this->formatTriggerType($alert->trigger_type, $locale);
            $lines[] = "• *{$symbol}* - {$type}";
        }

        $lines[] = '';
        $lines[] = $locale === 'ar' ? '📊 استخدم التطبيق لإدارة التنبيهات' : '📊 Use the app to manage alerts';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => implode("\n", $lines),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $locale === 'ar' ? '📊 إدارة التنبيهات' : '📊 Manage Alerts',
                        'web_app' => ['url' => config('app.url').'/dashboard'],
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

    private function formatTriggerType(string $type, string $locale): string
    {
        $types = [
            'target_price' => ['en' => 'Target Price', 'ar' => 'سعر مستهدف'],
            'breakout' => ['en' => 'Breakout', 'ar' => 'اختراق'],
            'daily_change' => ['en' => 'Daily Change', 'ar' => 'تغير يومي'],
            'signal' => ['en' => 'Technical Signal', 'ar' => 'إشارة فنية'],
            'prediction' => ['en' => 'AI Prediction', 'ar' => 'توقع ذكي'],
            'pattern' => ['en' => 'Chart Pattern', 'ar' => 'نموذج فني'],
        ];

        return $types[$type][$locale] ?? $type;
    }
}
