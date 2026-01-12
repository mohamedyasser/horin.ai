<?php

namespace App\Telegram\Handlers\Buttons;

use App\Models\User;
use App\Telegram\Commands\LanguageCommand;
use App\Telegram\Commands\SettingsCommand;
use App\Telegram\Keyboards\AlertsKeyboard;
use App\Telegram\Keyboards\SettingsKeyboard;

class SettingsButtonHandler extends AbstractButtonHandler
{
    public static function supportedActions(): array
    {
        return [
            'settings',
            'settings_profile',
            'settings_trading',
            'settings_markets',
            'settings_alerts',
            'settings_language',
            'profile_change_name',
            'alert_prefs_channels',
            'alert_prefs_limits',
            'limit_hour_decrease',
            'limit_hour_increase',
            'limit_day_decrease',
            'limit_day_increase',
        ];
    }

    public function showSettings(int $chatId, ?User $user, string $locale): mixed
    {
        $handler = new SettingsCommand($this->bot, $this->update);

        return $handler->handle();
    }

    public function showProfile(int $chatId, ?User $user, string $locale): mixed
    {
        $name = $user?->name ?? 'N/A';
        $phone = $user?->phone ?? 'N/A';
        $email = $user?->email ?? 'N/A';

        $text = $locale === 'ar'
            ? "👤 *الملف الشخصي*\n\n📛 الاسم: {$name}\n📱 الهاتف: {$phone}\n📧 البريد: {$email}\n\nاختر من القائمة أدناه:"
            : "👤 *Profile*\n\n📛 Name: {$name}\n📱 Phone: {$phone}\n📧 Email: {$email}\n\nChoose from the menu below:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => SettingsKeyboard::menu($locale),
        ]);
    }

    public function showTrading(int $chatId, ?User $user, string $locale): mixed
    {
        $experience = $user?->experience_level ?? 'Not set';
        $risk = $user?->risk_level ?? 'Not set';
        $style = $user?->trading_style ?? 'Not set';

        $text = $locale === 'ar'
            ? "📊 *ملف التداول*\n\n📈 الخبرة: {$experience}\n⚠️ المخاطرة: {$risk}\n🎯 الأسلوب: {$style}\n\nاختر من القائمة أدناه:"
            : "📊 *Trading Profile*\n\n📈 Experience: {$experience}\n⚠️ Risk Level: {$risk}\n🎯 Style: {$style}\n\nChoose from the menu below:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => SettingsKeyboard::menu($locale),
        ]);
    }

    public function showMarkets(int $chatId, ?User $user, string $locale): mixed
    {
        $markets = $user?->markets()->pluck('name')->join(', ') ?? 'None';

        $text = $locale === 'ar'
            ? "🌍 *الأسواق*\n\n📍 الأسواق المتابعة: {$markets}\n\nاختر من القائمة أدناه:"
            : "🌍 *Markets*\n\n📍 Followed Markets: {$markets}\n\nChoose from the menu below:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => SettingsKeyboard::menu($locale),
        ]);
    }

    public function showAlertSettings(int $chatId, ?User $user, string $locale): mixed
    {
        $telegramEnabled = $user?->telegram_notifications_enabled ? '✅' : '❌';
        $emailEnabled = $user?->email_notifications_enabled ? '✅' : '❌';

        $text = $locale === 'ar'
            ? "🔔 *إعدادات التنبيهات*\n\n📱 تيليجرام: {$telegramEnabled}\n📧 البريد: {$emailEnabled}\n\nاختر من القائمة أدناه:"
            : "🔔 *Alert Settings*\n\n📱 Telegram: {$telegramEnabled}\n📧 Email: {$emailEnabled}\n\nChoose from the menu below:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => SettingsKeyboard::menu($locale),
        ]);
    }

    public function showLanguage(int $chatId, ?User $user, string $locale): mixed
    {
        $handler = new LanguageCommand($this->bot, $this->update);

        return $handler->handle();
    }

    public function promptNameChange(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return (new NavigationButtonHandler($this->bot, $this->update))
                ->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['telegram_awaiting_input' => 'name']);

        $text = $locale === 'ar'
            ? "✏️ أدخل اسمك الجديد:\n\nاكتب اسمك في الرسالة التالية."
            : "✏️ Enter your new name:\n\nType your name in your next message.";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => AlertsKeyboard::cancelKeyboard($locale),
        ]);
    }

    public function showChannelsSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $channels = ['telegram', 'in_app'];
        if ($user) {
            $prefs = $user->getAlertPreferences();
            $channels = $prefs->channels ?? ['telegram', 'in_app'];
        }

        $text = $locale === 'ar'
            ? "📱 *قنوات الإشعارات*\n\nاختر القنوات لتلقي التنبيهات:\n\n⚠️ لتغيير القنوات، يرجى استخدام التطبيق."
            : "📱 *Notification Channels*\n\nSelect channels for receiving alerts:\n\n⚠️ To change channels, please use the app.";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => SettingsKeyboard::channelsKeyboard($channels, $locale),
        ]);
    }

    public function showLimitsSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $maxHour = 10;
        $maxDay = 25;

        if ($user) {
            $prefs = $user->getAlertPreferences();
            $maxHour = $prefs->max_alerts_per_hour ?? 10;
            $maxDay = $prefs->max_alerts_per_day ?? 25;
        }

        $text = $locale === 'ar'
            ? "🔢 *حدود التنبيهات*\n\nحدد الحد الأقصى للتنبيهات:"
            : "🔢 *Alert Limits*\n\nSet maximum alerts:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => SettingsKeyboard::limitsKeyboard($maxHour, $maxDay, $locale),
        ]);
    }

    public function adjustLimitHourDecrease(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->adjustLimit($chatId, $user, $locale, 'hour', -1);
    }

    public function adjustLimitHourIncrease(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->adjustLimit($chatId, $user, $locale, 'hour', 1);
    }

    public function adjustLimitDayDecrease(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->adjustLimit($chatId, $user, $locale, 'day', -5);
    }

    public function adjustLimitDayIncrease(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->adjustLimit($chatId, $user, $locale, 'day', 5);
    }

    private function adjustLimit(int $chatId, ?User $user, string $locale, string $type, int $delta): mixed
    {
        if (! $user) {
            return (new NavigationButtonHandler($this->bot, $this->update))
                ->goBackToMainMenu($chatId, $user, $locale);
        }

        $prefs = $user->getAlertPreferences();
        $maxHour = $prefs->max_alerts_per_hour ?? 10;
        $maxDay = $prefs->max_alerts_per_day ?? 25;

        if ($type === 'hour') {
            $maxHour = max(1, min(50, $maxHour + $delta));
            $prefs->update(['max_alerts_per_hour' => $maxHour]);
        } else {
            $maxDay = max(5, min(100, $maxDay + $delta));
            $prefs->update(['max_alerts_per_day' => $maxDay]);
        }

        $text = $locale === 'ar'
            ? "🔢 *حدود التنبيهات*\n\n✅ تم التحديث"
            : "🔢 *Alert Limits*\n\n✅ Updated";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => SettingsKeyboard::limitsKeyboard($maxHour, $maxDay, $locale),
        ]);
    }
}
