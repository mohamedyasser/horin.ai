<?php

namespace App\Telegram\Keyboards;

class SettingsKeyboard
{
    /**
     * Settings menu keyboard.
     */
    public static function menu(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '👤 الملف الشخصي'], ['text' => '📊 التداول']],
                    [['text' => '🌍 الأسواق'], ['text' => '🔔 إعدادات التنبيهات']],
                    [['text' => '🌐 اللغة']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '👤 Profile'], ['text' => '📊 Trading']],
                [['text' => '🌍 Markets'], ['text' => '🔔 Alert Settings']],
                [['text' => '🌐 Language']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Profile settings keyboard.
     */
    public static function profileKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '✏️ تغيير الاسم']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '✏️ Change Name']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Alert preferences keyboard.
     */
    public static function alertPreferencesKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📱 قنوات الإشعارات']],
                    [['text' => '🔢 حدود التنبيهات']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📱 Notification Channels']],
                [['text' => '🔢 Alert Limits']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Notification channels keyboard.
     */
    public static function channelsKeyboard(array $enabledChannels, string $locale): array
    {
        $telegramIcon = in_array('telegram', $enabledChannels, true) ? '✅' : '❌';
        $emailIcon = in_array('email', $enabledChannels, true) ? '✅' : '❌';
        $pushIcon = in_array('push', $enabledChannels, true) ? '✅' : '❌';
        $inAppIcon = in_array('in_app', $enabledChannels, true) ? '✅' : '❌';

        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => "{$telegramIcon} تيليجرام"]],
                    [['text' => "{$emailIcon} البريد الإلكتروني"]],
                    [['text' => "{$pushIcon} إشعارات الدفع"]],
                    [['text' => "{$inAppIcon} داخل التطبيق"]],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => "{$telegramIcon} Telegram"]],
                [['text' => "{$emailIcon} Email"]],
                [['text' => "{$pushIcon} Push Notifications"]],
                [['text' => "{$inAppIcon} In-App"]],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Alert limits keyboard.
     */
    public static function limitsKeyboard(int $maxHour, int $maxDay, string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => "⏰ في الساعة: {$maxHour}"]],
                    [['text' => '➖ أقل/ساعة'], ['text' => '➕ أكثر/ساعة']],
                    [['text' => "📅 في اليوم: {$maxDay}"]],
                    [['text' => '➖ أقل/يوم'], ['text' => '➕ أكثر/يوم']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => "⏰ Per Hour: {$maxHour}"]],
                [['text' => '➖ Less/Hour'], ['text' => '➕ More/Hour']],
                [['text' => "📅 Per Day: {$maxDay}"]],
                [['text' => '➖ Less/Day'], ['text' => '➕ More/Day']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }
}
