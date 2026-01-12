<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Telegram\Commands\AlertsCommand;
use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\OnboardingCommand;
use App\Telegram\Commands\SettingsCommand;
use App\Telegram\Services\DefaultKeyboardBuilder;
use WeStacks\TeleBot\Foundation\UpdateHandler;

/**
 * Handler for reply keyboard button clicks and unknown text inputs.
 *
 * This handler:
 * 1. Maps keyboard button text to appropriate commands
 * 2. Shows default keyboard for any unrecognized input
 */
class KeyboardButtonHandler extends UpdateHandler
{
    /**
     * Button text mappings to handlers (both English and Arabic).
     */
    private const BUTTON_MAPPINGS = [
        // Main menu buttons
        '📋 Alerts' => 'alerts',
        '📋 التنبيهات' => 'alerts',
        '➕ New Alert' => 'new_alert',
        '➕ تنبيه جديد' => 'new_alert',
        '⚙️ Settings' => 'settings',
        '⚙️ الإعدادات' => 'settings',
        '❓ Help' => 'help',
        '❓ مساعدة' => 'help',

        // Onboarding buttons
        '▶️ Continue Setup' => 'onboarding',
        '▶️ متابعة الإعداد' => 'onboarding',

        // Settings menu buttons
        '👤 Profile' => 'settings_profile',
        '👤 الملف الشخصي' => 'settings_profile',
        '📊 Trading' => 'settings_trading',
        '📊 التداول' => 'settings_trading',
        '🌍 Markets' => 'settings_markets',
        '🌍 الأسواق' => 'settings_markets',
        '🔔 Alert Settings' => 'settings_alerts',
        '🔔 إعدادات التنبيهات' => 'settings_alerts',
        '🌐 Language' => 'settings_language',
        '🌐 اللغة' => 'settings_language',

        // Alerts menu buttons
        '📋 My Alerts' => 'alerts_list',
        '📋 تنبيهاتي' => 'alerts_list',
        '📜 History' => 'alerts_history',
        '📜 السجل' => 'alerts_history',

        // Back button (context-aware)
        '◀️ Back' => 'back',
        '◀️ رجوع' => 'back',

        // Language selection buttons
        '🇬🇧 English' => 'set_language_en',
        '🇸🇦 العربية' => 'set_language_ar',

        // Phone share button (if user types it instead of using contact share)
        '📱 Share Phone Number' => 'phone_share_hint',
        '📱 مشاركة رقم الهاتف' => 'phone_share_hint',
    ];

    public function trigger(): bool
    {
        // Must be a text message (not command, not contact)
        if (! isset($this->update->message?->text)) {
            return false;
        }

        $text = $this->update->message->text;

        // Ignore commands (they have their own handlers)
        if (str_starts_with($text, '/')) {
            return false;
        }

        // Check if user is awaiting specific input (TextInputHandler handles that)
        $telegramId = (string) $this->update->message->from->id;
        $user = User::where('telegram_id', $telegramId)->first();

        if ($user && $user->telegram_awaiting_input) {
            return false;
        }

        // Don't handle if user is in onboarding (OnboardingTextHandler handles that)
        if ($user && $user->hasVerifiedPhone() && ! $user->hasCompletedOnboarding()) {
            return false;
        }

        return true;
    }

    public function handle(): mixed
    {
        $message = $this->update->message;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;
        $text = trim($message->text);

        $user = User::where('telegram_id', $telegramId)->first();
        $locale = $user?->language ?? $this->getLocaleFromTelegram();

        // Check if this is a known button
        $action = self::BUTTON_MAPPINGS[$text] ?? null;

        if ($action) {
            return $this->handleButtonAction($action, $chatId, $user, $locale);
        }

        // Unknown input - show default keyboard with explanation
        return $this->handleUnknownInput($chatId, $user, $locale);
    }

    private function handleButtonAction(string $action, int $chatId, ?User $user, string $locale): mixed
    {
        return match ($action) {
            'alerts' => $this->triggerAlertsCommand($chatId),
            'new_alert' => $this->triggerNewAlertCommand($chatId, $locale),
            'settings' => $this->triggerSettingsCommand($chatId),
            'help' => $this->triggerHelpCommand($chatId),
            'onboarding' => $this->triggerOnboardingCommand($chatId, $user),

            // Settings menu actions
            'settings_profile' => $this->showSettingsProfile($chatId, $user, $locale),
            'settings_trading' => $this->showSettingsTrading($chatId, $user, $locale),
            'settings_markets' => $this->showSettingsMarkets($chatId, $user, $locale),
            'settings_alerts' => $this->showSettingsAlerts($chatId, $user, $locale),
            'settings_language' => $this->showSettingsLanguage($chatId, $user, $locale),

            // Alerts menu actions
            'alerts_list' => $this->showAlertsList($chatId, $user, $locale),
            'alerts_history' => $this->showAlertsHistory($chatId, $user, $locale),

            // Back to main menu
            'back' => $this->goBackToMainMenu($chatId, $user, $locale),

            // Language selection
            'set_language_en' => $this->setUserLanguage($chatId, $user, 'en'),
            'set_language_ar' => $this->setUserLanguage($chatId, $user, 'ar'),

            // Phone share hint (user typed text instead of using contact share)
            'phone_share_hint' => $this->showPhoneShareHint($chatId, $locale),

            default => $this->handleUnknownInput($chatId, $user, $locale),
        };
    }

    private function triggerAlertsCommand(int $chatId): mixed
    {
        // Dispatch to AlertsCommand
        $handler = new AlertsCommand($this->bot, $this->update);

        return $handler->handle();
    }

    private function triggerNewAlertCommand(int $chatId, string $locale): mixed
    {
        // For new alert, we need to start the alert creation flow
        $text = $locale === 'ar'
            ? "➕ *إنشاء تنبيه جديد*\n\nاستخدم الأمر /alerts ثم اختر 'تنبيه جديد' لإنشاء تنبيه."
            : "➕ *Create New Alert*\n\nUse /alerts command then select 'New Alert' to create an alert.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        // Trigger alerts command
        return $this->triggerAlertsCommand($chatId);
    }

    private function triggerSettingsCommand(int $chatId): mixed
    {
        $handler = new SettingsCommand($this->bot, $this->update);

        return $handler->handle();
    }

    private function triggerHelpCommand(int $chatId): mixed
    {
        $handler = new HelpCommand($this->bot, $this->update);

        return $handler->handle();
    }

    private function triggerOnboardingCommand(int $chatId, ?User $user): mixed
    {
        $handler = new OnboardingCommand($this->bot, $this->update);

        return $handler->handle();
    }

    private function showSettingsProfile(int $chatId, ?User $user, string $locale): mixed
    {
        $name = $user?->name ?? 'N/A';
        $phone = $user?->phone ?? 'N/A';
        $email = $user?->email ?? 'N/A';

        $text = $locale === 'ar'
            ? "👤 *الملف الشخصي*\n\n📛 الاسم: {$name}\n📱 الهاتف: {$phone}\n📧 البريد: {$email}\n\nاختر من القائمة أدناه:"
            : "👤 *Profile*\n\n📛 Name: {$name}\n📱 Phone: {$phone}\n📧 Email: {$email}\n\nChoose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
        ]);

        return null;
    }

    private function showSettingsTrading(int $chatId, ?User $user, string $locale): mixed
    {
        $experience = $user?->experience_level ?? 'Not set';
        $risk = $user?->risk_level ?? 'Not set';
        $style = $user?->trading_style ?? 'Not set';

        $text = $locale === 'ar'
            ? "📊 *ملف التداول*\n\n📈 الخبرة: {$experience}\n⚠️ المخاطرة: {$risk}\n🎯 الأسلوب: {$style}\n\nاختر من القائمة أدناه:"
            : "📊 *Trading Profile*\n\n📈 Experience: {$experience}\n⚠️ Risk Level: {$risk}\n🎯 Style: {$style}\n\nChoose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
        ]);

        return null;
    }

    private function showSettingsMarkets(int $chatId, ?User $user, string $locale): mixed
    {
        $markets = $user?->markets()->pluck('name')->join(', ') ?? 'None';

        $text = $locale === 'ar'
            ? "🌍 *الأسواق*\n\n📍 الأسواق المتابعة: {$markets}\n\nاختر من القائمة أدناه:"
            : "🌍 *Markets*\n\n📍 Followed Markets: {$markets}\n\nChoose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
        ]);

        return null;
    }

    private function showSettingsAlerts(int $chatId, ?User $user, string $locale): mixed
    {
        $telegramEnabled = $user?->telegram_notifications_enabled ? '✅' : '❌';
        $emailEnabled = $user?->email_notifications_enabled ? '✅' : '❌';

        $text = $locale === 'ar'
            ? "🔔 *إعدادات التنبيهات*\n\n📱 تيليجرام: {$telegramEnabled}\n📧 البريد: {$emailEnabled}\n\nاختر من القائمة أدناه:"
            : "🔔 *Alert Settings*\n\n📱 Telegram: {$telegramEnabled}\n📧 Email: {$emailEnabled}\n\nChoose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
        ]);

        return null;
    }

    private function showSettingsLanguage(int $chatId, ?User $user, string $locale): mixed
    {
        $handler = new \App\Telegram\Commands\LanguageCommand($this->bot, $this->update);

        return $handler->handle();
    }

    private function showAlertsList(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $alerts = \App\Models\Alert::where('user_id', $user->id)
            ->active()
            ->with('asset')
            ->take(10)
            ->get();

        if ($alerts->isEmpty()) {
            $text = $locale === 'ar'
                ? "📋 *تنبيهاتك*\n\nلا توجد تنبيهات نشطة.\n\nاضغط '➕ تنبيه جديد' لإنشاء تنبيه."
                : "📋 *Your Alerts*\n\nNo active alerts.\n\nTap '➕ New Alert' to create one.";
        } else {
            $alertLines = $alerts->map(function ($alert) {
                $symbol = $alert->asset?->symbol ?? 'N/A';
                $condition = $alert->condition ?? 'N/A';
                $value = $alert->target_value ?? 'N/A';

                return "• {$symbol}: {$condition} {$value}";
            })->join("\n");

            $text = $locale === 'ar'
                ? "📋 *تنبيهاتك النشطة*\n\n{$alertLines}"
                : "📋 *Your Active Alerts*\n\n{$alertLines}";
        }

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
        ]);

        return null;
    }

    private function showAlertsHistory(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $alerts = \App\Models\Alert::where('user_id', $user->id)
            ->whereNotNull('last_triggered_at')
            ->orderBy('last_triggered_at', 'desc')
            ->with('asset')
            ->take(10)
            ->get();

        if ($alerts->isEmpty()) {
            $text = $locale === 'ar'
                ? "📜 *سجل التنبيهات*\n\nلا يوجد سجل تنبيهات."
                : "📜 *Alert History*\n\nNo alert history.";
        } else {
            $alertLines = $alerts->map(function ($alert) {
                $symbol = $alert->asset?->symbol ?? 'N/A';
                $date = $alert->last_triggered_at?->format('M d, H:i') ?? 'N/A';

                return "• {$symbol} - {$date}";
            })->join("\n");

            $text = $locale === 'ar'
                ? "📜 *سجل التنبيهات*\n\n{$alertLines}"
                : "📜 *Alert History*\n\n{$alertLines}";
        }

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
        ]);

        return null;
    }

    private function goBackToMainMenu(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "🏠 *القائمة الرئيسية*\n\nاختر من القائمة أدناه:"
            : "🏠 *Main Menu*\n\nChoose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::forUser($user, $locale),
        ]);

        return null;
    }

    private function setUserLanguage(int $chatId, ?User $user, string $newLocale): mixed
    {
        $message = $this->update->message;
        $telegramId = (string) $message->from->id;

        if ($user) {
            // Existing user - update language
            $user->update(['language' => $newLocale]);

            $text = $newLocale === 'ar'
                ? "✅ تم تغيير اللغة إلى العربية.\n\nاختر من القائمة أدناه:"
                : "✅ Language changed to English.\n\nChoose from the menu below:";

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => DefaultKeyboardBuilder::forUser($user, $newLocale),
            ]);
        } else {
            // New user selecting language - create user record with language preference
            $from = $message->from;
            $firstName = $from->first_name ?? '';
            $lastName = $from->last_name ?? '';
            $name = trim("{$firstName} {$lastName}") ?: 'Telegram User';

            $user = User::create([
                'name' => $name,
                'telegram_id' => $telegramId,
                'telegram_username' => $from->username ?? null,
                'language' => $newLocale,
            ]);

            \Illuminate\Support\Facades\Log::info('New user created via language selection', [
                'user_id' => $user->id,
                'telegram_id' => $telegramId,
                'language' => $newLocale,
            ]);

            // Show phone verification
            $text = $newLocale === 'ar'
                ? "✅ تم اختيار العربية.\n\n📱 يرجى مشاركة رقم هاتفك للمتابعة.\n\nاضغط الزر أدناه:"
                : "✅ English selected.\n\n📱 Please share your phone number to continue.\n\nTap the button below:";

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => DefaultKeyboardBuilder::phoneVerificationKeyboard($newLocale),
            ]);
        }

        return null;
    }

    private function showPhoneShareHint(int $chatId, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "📱 يرجى الضغط على الزر أدناه لمشاركة رقم هاتفك.\n\n⚠️ لا تكتب الرقم - اضغط الزر للمشاركة التلقائية."
            : "📱 Please tap the button below to share your phone number.\n\n⚠️ Don't type the text - tap the button to share automatically.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::phoneVerificationKeyboard($locale),
        ]);

        return null;
    }

    private function handleUnknownInput(int $chatId, ?User $user, string $locale): mixed
    {
        $response = DefaultKeyboardBuilder::getUnknownInputResponse($user, $locale);

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $response['text'],
            'reply_markup' => $response['reply_markup'],
        ]);

        return null;
    }

    private function getLocaleFromTelegram(): string
    {
        $langCode = $this->update->message->from->language_code ?? 'en';

        return str_starts_with($langCode, 'ar') ? 'ar' : 'en';
    }
}
