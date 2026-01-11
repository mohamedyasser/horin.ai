<?php

namespace App\Telegram\Commands;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class SettingsCommand extends CommandHandler
{
    protected static function command(): string
    {
        return 'settings';
    }

    protected static function description(): string
    {
        return 'View your notification settings';
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

        $locale = $user->language ?? 'en';
        $preferences = $user->getAlertPreferences();

        $langDisplay = $locale === 'ar' ? 'العربية' : 'English';
        $quietStart = $preferences->quiet_hours_start ?? '23:00';
        $quietEnd = $preferences->quiet_hours_end ?? '07:00';
        $maxHour = $preferences->max_alerts_per_hour ?? 10;
        $maxDay = $preferences->max_alerts_per_day ?? 25;

        if ($locale === 'ar') {
            $text = <<<MSG
⚙️ *إعداداتك*
━━━━━━━━━━━━━━━━━━

🌐 اللغة: {$langDisplay}
🌙 ساعات الهدوء: {$quietStart} - {$quietEnd}
📊 الحد الأقصى/ساعة: {$maxHour}
📊 الحد الأقصى/يوم: {$maxDay}

استخدم التطبيق لتعديل الإعدادات.
MSG;
        } else {
            $text = <<<MSG
⚙️ *Your Settings*
━━━━━━━━━━━━━━━━━━

🌐 Language: {$langDisplay}
🌙 Quiet Hours: {$quietStart} - {$quietEnd}
📊 Max alerts/hour: {$maxHour}
📊 Max alerts/day: {$maxDay}

Use the app to modify settings.
MSG;
        }

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $locale === 'ar' ? '⚙️ فتح الإعدادات' : '⚙️ Open Settings',
                        'url' => config('app.url').'/settings',
                    ],
                ]],
            ],
        ]);

        return null;
    }
}
