<?php

namespace App\Telegram\Handlers;

use App\Models\Alert;
use App\Models\User;
use App\Telegram\Commands\AlertsCommand;
use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\OnboardingCommand;
use App\Telegram\Commands\SettingsCommand;
use App\Telegram\Services\AlertKeyboardBuilder;
use App\Telegram\Services\DefaultKeyboardBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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

        // Alert type selection buttons
        '💰 Price Alert' => 'alert_type_price',
        '💰 تنبيه السعر' => 'alert_type_price',
        '📈 Signal Alert' => 'alert_type_signal',
        '📈 تنبيه إشارة' => 'alert_type_signal',
        '🔮 Prediction Alert' => 'alert_type_prediction',
        '🔮 تنبيه توقع' => 'alert_type_prediction',

        // Back button (context-aware)
        '◀️ Back' => 'back',
        '◀️ رجوع' => 'back',

        // Language selection buttons
        '🇬🇧 English' => 'set_language_en',
        '🇸🇦 العربية' => 'set_language_ar',

        // Phone share button (if user types it instead of using contact share)
        '📱 Share Phone Number' => 'phone_share_hint',
        '📱 مشاركة رقم الهاتف' => 'phone_share_hint',

        // Profile settings buttons
        '✏️ Change Name' => 'profile_change_name',
        '✏️ تغيير الاسم' => 'profile_change_name',

        // Trading settings buttons
        '📈 Experience Level' => 'trading_experience',
        '📈 مستوى الخبرة' => 'trading_experience',
        '⚠️ Risk Level' => 'trading_risk',
        '⚠️ مستوى المخاطرة' => 'trading_risk',
        '🎯 Investment Goal' => 'trading_goal',
        '🎯 الهدف الاستثماري' => 'trading_goal',
        '📊 Trading Style' => 'trading_style',
        '📊 أسلوب التداول' => 'trading_style',

        // Experience level buttons
        '🌱 Beginner' => 'set_experience_beginner',
        '🌱 مبتدئ' => 'set_experience_beginner',
        '📊 Intermediate' => 'set_experience_intermediate',
        '📊 متوسط' => 'set_experience_intermediate',
        '🎓 Advanced' => 'set_experience_advanced',
        '🎓 متقدم' => 'set_experience_advanced',

        // Risk level buttons
        '🛡️ Conservative' => 'set_risk_conservative',
        '🛡️ محافظ' => 'set_risk_conservative',
        '⚖️ Moderate' => 'set_risk_moderate',
        '⚖️ معتدل' => 'set_risk_moderate',
        '🔥 Aggressive' => 'set_risk_aggressive',
        '🔥 مغامر' => 'set_risk_aggressive',

        // Investment goal buttons
        '📈 Capital Growth' => 'set_goal_capital_growth',
        '📈 نمو رأس المال' => 'set_goal_capital_growth',
        '💵 Fixed Income' => 'set_goal_fixed_income',
        '💵 دخل ثابت' => 'set_goal_fixed_income',
        '🛡️ Risk Reduction' => 'set_goal_risk_reduction',
        '🛡️ تقليل المخاطر' => 'set_goal_risk_reduction',
        '⚡ Short-term Speculation' => 'set_goal_short_term',
        '⚡ مضاربة قصيرة' => 'set_goal_short_term',

        // Trading style buttons
        '📅 Day Trading' => 'set_style_day_trading',
        '📅 تداول يومي' => 'set_style_day_trading',
        '📊 Swing Trading' => 'set_style_swing_trading',
        '📊 تداول متأرجح' => 'set_style_swing_trading',
        '📈 Position Trading' => 'set_style_position_trading',
        '📈 تداول مراكز' => 'set_style_position_trading',
        '⚡ Scalping' => 'set_style_scalping',
        '⚡ سكالبينج' => 'set_style_scalping',

        // Markets settings buttons
        '🌍 Country' => 'markets_country',
        '🌍 الدولة' => 'markets_country',
        '🏛️ Markets' => 'markets_markets',
        '🏛️ الأسواق' => 'markets_markets',
        '📂 Sectors' => 'markets_sectors',
        '📂 القطاعات' => 'markets_sectors',

        // Alert preferences buttons
        '📱 Notification Channels' => 'alert_prefs_channels',
        '📱 قنوات الإشعارات' => 'alert_prefs_channels',
        '🔢 Alert Limits' => 'alert_prefs_limits',
        '🔢 حدود التنبيهات' => 'alert_prefs_limits',

        // Alert limits buttons
        '➖ Less/Hour' => 'limit_hour_decrease',
        '➖ أقل/ساعة' => 'limit_hour_decrease',
        '➕ More/Hour' => 'limit_hour_increase',
        '➕ أكثر/ساعة' => 'limit_hour_increase',
        '➖ Less/Day' => 'limit_day_decrease',
        '➖ أقل/يوم' => 'limit_day_decrease',
        '➕ More/Day' => 'limit_day_increase',
        '➕ أكثر/يوم' => 'limit_day_increase',

        // Cancel button
        '❌ Cancel' => 'cancel_input',
        '❌ إلغاء' => 'cancel_input',

        // Alert trigger type buttons
        '🎯 Target Price' => 'alert_trigger_target_price',
        '🎯 سعر مستهدف' => 'alert_trigger_target_price',
        '📊 Daily Change' => 'alert_trigger_daily_change',
        '📊 تغير يومي' => 'alert_trigger_daily_change',
        '📈 Price Breakout' => 'alert_trigger_breakout',
        '📈 اختراق سعر' => 'alert_trigger_breakout',

        // Alert direction buttons
        '⬆️ Above' => 'alert_direction_above',
        '⬆️ أعلى من' => 'alert_direction_above',
        '⬇️ Below' => 'alert_direction_below',
        '⬇️ أقل من' => 'alert_direction_below',
        '↕️ Either Direction' => 'alert_direction_both',
        '↕️ أي اتجاه' => 'alert_direction_both',

        // Alert confirmation buttons
        '✅ Confirm Create' => 'alert_confirm_create',
        '✅ تأكيد الإنشاء' => 'alert_confirm_create',

        // Alert action buttons
        '😴 Snooze' => 'alert_snooze',
        '😴 تأجيل' => 'alert_snooze',
        '⏰ Unsnooze' => 'alert_unsnooze',
        '⏰ إلغاء التأجيل' => 'alert_unsnooze',
        '⏸️ Pause' => 'alert_pause',
        '⏸️ إيقاف مؤقت' => 'alert_pause',
        '▶️ Resume' => 'alert_resume',
        '▶️ تفعيل' => 'alert_resume',
        '🗑️ Delete' => 'alert_delete',
        '🗑️ حذف' => 'alert_delete',

        // Snooze preset buttons
        '⏰ 1 Hour' => 'snooze_1h',
        '⏰ ساعة واحدة' => 'snooze_1h',
        '⏰ 4 Hours' => 'snooze_4h',
        '⏰ 4 ساعات' => 'snooze_4h',
        '📅 1 Day' => 'snooze_1d',
        '📅 يوم واحد' => 'snooze_1d',
        '🔔 Until Market Close' => 'snooze_market_close',
        '🔔 حتى إغلاق السوق' => 'snooze_market_close',

        // Delete confirmation buttons
        '🗑️ Yes, Delete' => 'alert_delete_confirm',
        '🗑️ نعم، احذف' => 'alert_delete_confirm',
        '◀️ No, Go Back' => 'back',
        '◀️ لا، رجوع' => 'back',

        // Asset search button
        '🔍 Search Asset' => 'alert_search_asset',
        '🔍 بحث عن أصل' => 'alert_search_asset',
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
            \Illuminate\Support\Facades\Log::debug('KeyboardButtonHandler: skipping - user in onboarding', [
                'telegram_id' => $telegramId,
            ]);

            return false;
        }

        \Illuminate\Support\Facades\Log::debug('KeyboardButtonHandler: triggering', [
            'telegram_id' => $telegramId,
            'user_exists' => $user !== null,
            'phone_verified' => $user?->hasVerifiedPhone(),
            'onboarding_complete' => $user?->hasCompletedOnboarding(),
            'phone_verified_at' => $user?->phone_verified_at,
        ]);

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
            'new_alert' => $this->triggerNewAlertCommand($chatId, $user, $locale),
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

            // Alert type selection
            'alert_type_price' => $this->selectAlertType($chatId, $user, $locale, 'price'),
            'alert_type_signal' => $this->selectAlertType($chatId, $user, $locale, 'signal'),
            'alert_type_prediction' => $this->selectAlertType($chatId, $user, $locale, 'prediction'),

            // Back to main menu
            'back' => $this->goBackToMainMenu($chatId, $user, $locale),

            // Language selection
            'set_language_en' => $this->setUserLanguage($chatId, $user, 'en'),
            'set_language_ar' => $this->setUserLanguage($chatId, $user, 'ar'),

            // Phone share hint (user typed text instead of using contact share)
            'phone_share_hint' => $this->showPhoneShareHint($chatId, $locale),

            // Profile settings
            'profile_change_name' => $this->promptNameChange($chatId, $user, $locale),

            // Trading settings
            'trading_experience' => $this->showExperienceSelector($chatId, $user, $locale),
            'trading_risk' => $this->showRiskSelector($chatId, $user, $locale),
            'trading_goal' => $this->showGoalSelector($chatId, $user, $locale),
            'trading_style' => $this->showStyleSelector($chatId, $user, $locale),

            // Experience level selection
            'set_experience_beginner' => $this->setExperience($chatId, $user, $locale, 'beginner'),
            'set_experience_intermediate' => $this->setExperience($chatId, $user, $locale, 'intermediate'),
            'set_experience_advanced' => $this->setExperience($chatId, $user, $locale, 'advanced'),

            // Risk level selection
            'set_risk_conservative' => $this->setRiskLevel($chatId, $user, $locale, 'conservative'),
            'set_risk_moderate' => $this->setRiskLevel($chatId, $user, $locale, 'moderate'),
            'set_risk_aggressive' => $this->setRiskLevel($chatId, $user, $locale, 'aggressive'),

            // Investment goal selection
            'set_goal_capital_growth' => $this->setGoal($chatId, $user, $locale, 'capital_growth'),
            'set_goal_fixed_income' => $this->setGoal($chatId, $user, $locale, 'fixed_income'),
            'set_goal_risk_reduction' => $this->setGoal($chatId, $user, $locale, 'risk_reduction'),
            'set_goal_short_term' => $this->setGoal($chatId, $user, $locale, 'short_term_speculation'),

            // Trading style selection
            'set_style_day_trading' => $this->setStyle($chatId, $user, $locale, 'day_trading'),
            'set_style_swing_trading' => $this->setStyle($chatId, $user, $locale, 'swing_trading'),
            'set_style_position_trading' => $this->setStyle($chatId, $user, $locale, 'position_trading'),
            'set_style_scalping' => $this->setStyle($chatId, $user, $locale, 'scalping_trading'),

            // Markets settings
            'markets_country' => $this->showCountryInfo($chatId, $user, $locale),
            'markets_markets' => $this->showMarketsInfo($chatId, $user, $locale),
            'markets_sectors' => $this->showSectorsInfo($chatId, $user, $locale),

            // Alert preferences
            'alert_prefs_channels' => $this->showChannelsSelector($chatId, $user, $locale),
            'alert_prefs_limits' => $this->showLimitsSelector($chatId, $user, $locale),

            // Alert limits adjustment
            'limit_hour_decrease' => $this->adjustLimit($chatId, $user, $locale, 'hour', -1),
            'limit_hour_increase' => $this->adjustLimit($chatId, $user, $locale, 'hour', 1),
            'limit_day_decrease' => $this->adjustLimit($chatId, $user, $locale, 'day', -5),
            'limit_day_increase' => $this->adjustLimit($chatId, $user, $locale, 'day', 5),

            // Cancel input
            'cancel_input' => $this->cancelInput($chatId, $user, $locale),

            // Alert trigger type selection
            'alert_trigger_target_price' => $this->setAlertTriggerType($chatId, $user, $locale, 'target_price'),
            'alert_trigger_daily_change' => $this->setAlertTriggerType($chatId, $user, $locale, 'daily_change'),
            'alert_trigger_breakout' => $this->setAlertTriggerType($chatId, $user, $locale, 'breakout'),

            // Alert direction selection
            'alert_direction_above' => $this->setAlertDirection($chatId, $user, $locale, 'above'),
            'alert_direction_below' => $this->setAlertDirection($chatId, $user, $locale, 'below'),
            'alert_direction_both' => $this->setAlertDirection($chatId, $user, $locale, 'both'),

            // Alert confirmation
            'alert_confirm_create' => $this->confirmCreateAlert($chatId, $user, $locale),

            // Alert actions
            'alert_snooze' => $this->showSnoozeOptions($chatId, $user, $locale),
            'alert_unsnooze' => $this->unsnoozeAlert($chatId, $user, $locale),
            'alert_pause' => $this->toggleAlertStatus($chatId, $user, $locale),
            'alert_resume' => $this->toggleAlertStatus($chatId, $user, $locale),
            'alert_delete' => $this->showDeleteConfirmation($chatId, $user, $locale),

            // Snooze presets
            'snooze_1h' => $this->applySnooze($chatId, $user, $locale, '1h'),
            'snooze_4h' => $this->applySnooze($chatId, $user, $locale, '4h'),
            'snooze_1d' => $this->applySnooze($chatId, $user, $locale, '1d'),
            'snooze_market_close' => $this->applySnooze($chatId, $user, $locale, 'market_close'),

            // Delete confirmation
            'alert_delete_confirm' => $this->executeDeleteAlert($chatId, $user, $locale),

            // Asset search
            'alert_search_asset' => $this->promptAssetSearch($chatId, $user, $locale),

            default => $this->handleUnknownInput($chatId, $user, $locale),
        };
    }

    private function triggerAlertsCommand(int $chatId): mixed
    {
        // Dispatch to AlertsCommand
        $handler = new AlertsCommand($this->bot, $this->update);

        return $handler->handle();
    }

    private function triggerNewAlertCommand(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            $text = $locale === 'ar'
                ? '❌ يرجى التسجيل أولاً باستخدام /start'
                : '❌ Please register first using /start';

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            return null;
        }

        // Clear any existing draft and start fresh
        $user->update(['telegram_alert_draft' => ['step' => 'type']]);

        $text = $locale === 'ar'
            ? "📊 *إنشاء تنبيه*\n\nما نوع التنبيه؟"
            : "📊 *Create Alert*\n\nWhat type of alert?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::alertTypeKeyboard($locale),
        ]);

        return null;
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

    private function selectAlertType(int $chatId, ?User $user, string $locale, string $type): mixed
    {
        if (! $user) {
            $text = $locale === 'ar'
                ? '❌ يرجى التسجيل أولاً باستخدام /start'
                : '❌ Please register first using /start';

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            return null;
        }

        // Update draft with selected type
        $draft = $user->telegram_alert_draft ?? [];
        $draft['type'] = $type;
        $draft['step'] = 'asset';
        $user->update(['telegram_alert_draft' => $draft]);

        // Type labels for the message
        $typeLabels = [
            'price' => ['en' => 'Price Alert', 'ar' => 'تنبيه السعر', 'icon' => '💰'],
            'signal' => ['en' => 'Signal Alert', 'ar' => 'تنبيه إشارة', 'icon' => '📈'],
            'prediction' => ['en' => 'Prediction Alert', 'ar' => 'تنبيه توقع', 'icon' => '🔮'],
        ];

        $typeLabel = $typeLabels[$type][$locale] ?? $type;
        $typeIcon = $typeLabels[$type]['icon'] ?? '📊';

        // Get user's portfolio and watchlist assets for quick selection
        $portfolioAssets = $user->portfolio()->with('asset')->get()->pluck('asset')->filter();
        $watchlistAssets = $user->watchlist ?? collect();

        $builder = new AlertKeyboardBuilder;

        $text = $locale === 'ar'
            ? "{$typeIcon} *{$typeLabel}*\n\nاختر الأصل للتنبيه:"
            : "{$typeIcon} *{$typeLabel}*\n\nSelect an asset for the alert:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildAssetSelector($portfolioAssets, $watchlistAssets, $locale),
            ],
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

    private function promptNameChange(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['telegram_awaiting_input' => 'name']);

        $text = $locale === 'ar'
            ? "✏️ أدخل اسمك الجديد:\n\nاكتب اسمك في الرسالة التالية."
            : "✏️ Enter your new name:\n\nType your name in your next message.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
        ]);

        return null;
    }

    private function showExperienceSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "📈 *مستوى الخبرة*\n\nما هو مستوى خبرتك في التداول؟"
            : "📈 *Experience Level*\n\nWhat's your trading experience?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::experienceKeyboard($locale),
        ]);

        return null;
    }

    private function showRiskSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "⚠️ *مستوى المخاطرة*\n\nما هو مستوى تحملك للمخاطر؟"
            : "⚠️ *Risk Level*\n\nWhat's your risk tolerance?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::riskKeyboard($locale),
        ]);

        return null;
    }

    private function showGoalSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "🎯 *الهدف الاستثماري*\n\nما هو هدفك الاستثماري؟"
            : "🎯 *Investment Goal*\n\nWhat's your investment goal?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::goalKeyboard($locale),
        ]);

        return null;
    }

    private function showStyleSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "📊 *أسلوب التداول*\n\nما هو أسلوب التداول المفضل لديك؟"
            : "📊 *Trading Style*\n\nWhat's your preferred trading style?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::styleKeyboard($locale),
        ]);

        return null;
    }

    private function setExperience(int $chatId, ?User $user, string $locale, string $level): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['experience_level' => $level]);

        $labels = [
            'beginner' => ['en' => 'Beginner', 'ar' => 'مبتدئ'],
            'intermediate' => ['en' => 'Intermediate', 'ar' => 'متوسط'],
            'advanced' => ['en' => 'Advanced', 'ar' => 'متقدم'],
        ];

        $label = $labels[$level][$locale] ?? $level;

        $text = $locale === 'ar'
            ? "✅ تم تحديث مستوى الخبرة إلى: {$label}"
            : "✅ Experience level updated to: {$label}";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::tradingKeyboard($locale),
        ]);

        return null;
    }

    private function setRiskLevel(int $chatId, ?User $user, string $locale, string $level): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['risk_level' => $level]);

        $labels = [
            'conservative' => ['en' => 'Conservative', 'ar' => 'محافظ'],
            'moderate' => ['en' => 'Moderate', 'ar' => 'معتدل'],
            'aggressive' => ['en' => 'Aggressive', 'ar' => 'مغامر'],
        ];

        $label = $labels[$level][$locale] ?? $level;

        $text = $locale === 'ar'
            ? "✅ تم تحديث مستوى المخاطرة إلى: {$label}"
            : "✅ Risk level updated to: {$label}";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::tradingKeyboard($locale),
        ]);

        return null;
    }

    private function setGoal(int $chatId, ?User $user, string $locale, string $goal): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['investment_goal' => $goal]);

        $labels = [
            'capital_growth' => ['en' => 'Capital Growth', 'ar' => 'نمو رأس المال'],
            'fixed_income' => ['en' => 'Fixed Income', 'ar' => 'دخل ثابت'],
            'risk_reduction' => ['en' => 'Risk Reduction', 'ar' => 'تقليل المخاطر'],
            'short_term_speculation' => ['en' => 'Short-term Speculation', 'ar' => 'مضاربة قصيرة'],
        ];

        $label = $labels[$goal][$locale] ?? $goal;

        $text = $locale === 'ar'
            ? "✅ تم تحديث الهدف الاستثماري إلى: {$label}"
            : "✅ Investment goal updated to: {$label}";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::tradingKeyboard($locale),
        ]);

        return null;
    }

    private function setStyle(int $chatId, ?User $user, string $locale, string $style): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['trading_style' => $style]);

        $labels = [
            'day_trading' => ['en' => 'Day Trading', 'ar' => 'تداول يومي'],
            'swing_trading' => ['en' => 'Swing Trading', 'ar' => 'تداول متأرجح'],
            'position_trading' => ['en' => 'Position Trading', 'ar' => 'تداول مراكز'],
            'scalping_trading' => ['en' => 'Scalping', 'ar' => 'سكالبينج'],
        ];

        $label = $labels[$style][$locale] ?? $style;

        $text = $locale === 'ar'
            ? "✅ تم تحديث أسلوب التداول إلى: {$label}"
            : "✅ Trading style updated to: {$label}";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::tradingKeyboard($locale),
        ]);

        return null;
    }

    private function showCountryInfo(int $chatId, ?User $user, string $locale): mixed
    {
        $countryName = 'Not set';
        if ($user?->country) {
            $countryName = $locale === 'ar' ? $user->country->name_ar : $user->country->name_en;
        }

        $text = $locale === 'ar'
            ? "🌍 *الدولة*\n\nالدولة الحالية: {$countryName}\n\n⚠️ لتغيير الدولة، يرجى استخدام التطبيق."
            : "🌍 *Country*\n\nCurrent country: {$countryName}\n\n⚠️ To change country, please use the app.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::marketsSettingsKeyboard($locale),
        ]);

        return null;
    }

    private function showMarketsInfo(int $chatId, ?User $user, string $locale): mixed
    {
        $marketNames = 'None';
        if ($user) {
            $markets = $user->markets()->get();
            if ($markets->isNotEmpty()) {
                $marketNames = $markets->map(fn ($m) => $locale === 'ar' ? ($m->name_ar ?: $m->name_en) : $m->name_en)->join(', ');
            }
        }

        $text = $locale === 'ar'
            ? "🏛️ *الأسواق*\n\nالأسواق المتابعة: {$marketNames}\n\n⚠️ لتغيير الأسواق، يرجى استخدام التطبيق."
            : "🏛️ *Markets*\n\nFollowed markets: {$marketNames}\n\n⚠️ To change markets, please use the app.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::marketsSettingsKeyboard($locale),
        ]);

        return null;
    }

    private function showSectorsInfo(int $chatId, ?User $user, string $locale): mixed
    {
        $sectorNames = 'None';
        if ($user) {
            $sectors = $user->sectors()->get();
            if ($sectors->isNotEmpty()) {
                $sectorNames = $sectors->map(fn ($s) => $locale === 'ar' ? ($s->name_ar ?: $s->name_en) : $s->name_en)->join(', ');
            }
        }

        $text = $locale === 'ar'
            ? "📂 *القطاعات*\n\nالقطاعات المتابعة: {$sectorNames}\n\n⚠️ لتغيير القطاعات، يرجى استخدام التطبيق."
            : "📂 *Sectors*\n\nFollowed sectors: {$sectorNames}\n\n⚠️ To change sectors, please use the app.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::marketsSettingsKeyboard($locale),
        ]);

        return null;
    }

    private function showChannelsSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $channels = ['telegram', 'in_app'];
        if ($user) {
            $prefs = $user->getAlertPreferences();
            $channels = $prefs->channels ?? ['telegram', 'in_app'];
        }

        $text = $locale === 'ar'
            ? "📱 *قنوات الإشعارات*\n\nاختر القنوات لتلقي التنبيهات:\n\n⚠️ لتغيير القنوات، يرجى استخدام التطبيق."
            : "📱 *Notification Channels*\n\nSelect channels for receiving alerts:\n\n⚠️ To change channels, please use the app.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::channelsKeyboard($channels, $locale),
        ]);

        return null;
    }

    private function showLimitsSelector(int $chatId, ?User $user, string $locale): mixed
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

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::limitsKeyboard($maxHour, $maxDay, $locale),
        ]);

        return null;
    }

    private function adjustLimit(int $chatId, ?User $user, string $locale, string $type, int $delta): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
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

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::limitsKeyboard($maxHour, $maxDay, $locale),
        ]);

        return null;
    }

    private function cancelInput(int $chatId, ?User $user, string $locale): mixed
    {
        if ($user) {
            $user->update(['telegram_awaiting_input' => null]);
        }

        return $this->goBackToMainMenu($chatId, $user, $locale);
    }

    private function setAlertTriggerType(int $chatId, ?User $user, string $locale, string $triggerType): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['trigger_type'] = $triggerType;
        $draft['step'] = 'value';
        $user->update(['telegram_alert_draft' => $draft]);

        $labels = [
            'target_price' => ['en' => 'Target Price', 'ar' => 'سعر مستهدف'],
            'daily_change' => ['en' => 'Daily Change', 'ar' => 'تغير يومي'],
            'breakout' => ['en' => 'Price Breakout', 'ar' => 'اختراق سعر'],
        ];

        $label = $labels[$triggerType][$locale] ?? $triggerType;
        $symbol = $draft['asset_symbol'] ?? 'N/A';

        // For target_price and breakout, prompt for price value
        if ($triggerType === 'target_price' || $triggerType === 'breakout') {
            $user->update(['telegram_awaiting_input' => 'alert_target_price']);

            $text = $locale === 'ar'
                ? "🎯 *{$label}*\n\n📊 الأصل: {$symbol}\n\nأدخل السعر المستهدف:"
                : "🎯 *{$label}*\n\n📊 Asset: {$symbol}\n\nEnter the target price:";

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
            ]);
        } else {
            // For daily_change, prompt for percentage
            $user->update(['telegram_awaiting_input' => 'alert_percentage']);

            $text = $locale === 'ar'
                ? "📊 *{$label}*\n\n📊 الأصل: {$symbol}\n\nأدخل نسبة التغير (مثال: 5):"
                : "📊 *{$label}*\n\n📊 Asset: {$symbol}\n\nEnter the change percentage (e.g., 5):";

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
            ]);
        }

        return null;
    }

    private function setAlertDirection(int $chatId, ?User $user, string $locale, string $direction): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['direction'] = $direction;
        $draft['step'] = 'confirm';
        $user->update(['telegram_alert_draft' => $draft]);

        $directionLabels = [
            'above' => ['en' => 'Above', 'ar' => 'أعلى من', 'icon' => '⬆️'],
            'below' => ['en' => 'Below', 'ar' => 'أقل من', 'icon' => '⬇️'],
            'both' => ['en' => 'Either Direction', 'ar' => 'أي اتجاه', 'icon' => '↕️'],
        ];

        $dirLabel = $directionLabels[$direction][$locale] ?? $direction;
        $dirIcon = $directionLabels[$direction]['icon'] ?? '';

        $symbol = $draft['asset_symbol'] ?? 'N/A';
        $triggerType = $draft['trigger_type'] ?? 'target_price';
        $value = $draft['parameters']['target_price'] ?? $draft['parameters']['threshold_percent'] ?? 'N/A';

        $valueStr = is_numeric($value) ? number_format($value, 2) : $value;
        if ($triggerType === 'daily_change') {
            $valueStr .= '%';
        }

        $text = $locale === 'ar'
            ? "✅ *تأكيد التنبيه*\n\n📊 الأصل: {$symbol}\n📈 النوع: {$triggerType}\n🎯 القيمة: {$valueStr}\n{$dirIcon} الاتجاه: {$dirLabel}\n\nهل تريد إنشاء هذا التنبيه؟"
            : "✅ *Confirm Alert*\n\n📊 Asset: {$symbol}\n📈 Type: {$triggerType}\n🎯 Value: {$valueStr}\n{$dirIcon} Direction: {$dirLabel}\n\nCreate this alert?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::alertConfirmKeyboard($locale),
        ]);

        return null;
    }

    private function confirmCreateAlert(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];

        if (empty($draft['asset_id']) || empty($draft['trigger_type'])) {
            $text = $locale === 'ar'
                ? '❌ بيانات التنبيه غير مكتملة. يرجى البدء من جديد.'
                : '❌ Alert data incomplete. Please start again.';

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
            ]);

            return null;
        }

        // Create the alert
        $alertData = [
            'user_id' => $user->id,
            'asset_id' => $draft['asset_id'],
            'type' => $draft['type'] ?? 'price',
            'trigger_type' => $draft['trigger_type'],
            'condition' => $draft['direction'] ?? 'above',
            'is_active' => true,
        ];

        if (isset($draft['parameters']['target_price'])) {
            $alertData['target_value'] = $draft['parameters']['target_price'];
        }
        if (isset($draft['parameters']['threshold_percent'])) {
            $alertData['threshold_percent'] = $draft['parameters']['threshold_percent'];
        }

        $alert = Alert::create($alertData);

        Log::info('Alert created via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'type' => $alert->type,
        ]);

        // Clear draft
        $user->update(['telegram_alert_draft' => null]);

        $symbol = $draft['asset_symbol'] ?? 'N/A';

        $text = $locale === 'ar'
            ? "✅ *تم إنشاء التنبيه*\n\n📊 الأصل: {$symbol}\n\nسيتم إشعارك عند تحقق الشرط."
            : "✅ *Alert Created*\n\n📊 Asset: {$symbol}\n\nYou'll be notified when the condition is met.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::alertSuccessKeyboard($locale),
        ]);

        return null;
    }

    private function showSnoozeOptions(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $text = $locale === 'ar'
            ? "😴 *تأجيل التنبيه*\n\nاختر مدة التأجيل:"
            : "😴 *Snooze Alert*\n\nSelect snooze duration:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::snoozeOptionsKeyboard($locale),
        ]);

        return null;
    }

    private function unsnoozeAlert(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $text = $locale === 'ar'
                ? '❌ التنبيه غير موجود'
                : '❌ Alert not found';

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
            ]);

            return null;
        }

        $alert->update(['snoozed_until' => null]);

        Log::info('Alert unsnoozed via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
        ]);

        $text = $locale === 'ar'
            ? '✅ تم إلغاء تأجيل التنبيه'
            : '✅ Alert unsnoozed';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
        ]);

        return null;
    }

    private function toggleAlertStatus(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $text = $locale === 'ar'
                ? '❌ التنبيه غير موجود'
                : '❌ Alert not found';

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
            ]);

            return null;
        }

        $newStatus = ! $alert->is_active;
        $alert->update(['is_active' => $newStatus]);

        Log::info('Alert status toggled via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'is_active' => $newStatus,
        ]);

        $text = $newStatus
            ? ($locale === 'ar' ? '✅ تم تفعيل التنبيه' : '✅ Alert resumed')
            : ($locale === 'ar' ? '⏸️ تم إيقاف التنبيه مؤقتاً' : '⏸️ Alert paused');

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
        ]);

        return null;
    }

    private function showDeleteConfirmation(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->with('asset')
            ->first();

        if (! $alert) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $symbol = $alert->asset?->symbol ?? 'N/A';

        $text = $locale === 'ar'
            ? "🗑️ *حذف التنبيه*\n\n📊 الأصل: {$symbol}\n\n⚠️ هل أنت متأكد من حذف هذا التنبيه؟"
            : "🗑️ *Delete Alert*\n\n📊 Asset: {$symbol}\n\n⚠️ Are you sure you want to delete this alert?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::deleteConfirmKeyboard($locale),
        ]);

        return null;
    }

    private function applySnooze(int $chatId, ?User $user, string $locale, string $duration): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $text = $locale === 'ar'
                ? '❌ التنبيه غير موجود'
                : '❌ Alert not found';

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
            ]);

            return null;
        }

        $snoozedUntil = match ($duration) {
            '1h' => Carbon::now()->addHour(),
            '4h' => Carbon::now()->addHours(4),
            '1d' => Carbon::now()->addDay(),
            'market_close' => Carbon::now()->setTime(16, 0, 0),
            default => Carbon::now()->addHour(),
        };

        $alert->update(['snoozed_until' => $snoozedUntil]);

        Log::info('Alert snoozed via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'duration' => $duration,
            'snoozed_until' => $snoozedUntil,
        ]);

        $durationLabels = [
            '1h' => ['en' => '1 hour', 'ar' => 'ساعة واحدة'],
            '4h' => ['en' => '4 hours', 'ar' => '4 ساعات'],
            '1d' => ['en' => '1 day', 'ar' => 'يوم واحد'],
            'market_close' => ['en' => 'market close', 'ar' => 'إغلاق السوق'],
        ];

        $durationLabel = $durationLabels[$duration][$locale] ?? $duration;

        $text = $locale === 'ar'
            ? "😴 تم تأجيل التنبيه لمدة {$durationLabel}"
            : "😴 Alert snoozed for {$durationLabel}";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
        ]);

        return null;
    }

    private function executeDeleteAlert(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $text = $locale === 'ar'
                ? '❌ التنبيه غير موجود'
                : '❌ Alert not found';

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
            ]);

            return null;
        }

        Log::info('Alert deleted via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
        ]);

        $alert->delete();

        // Clear viewing alert from draft
        $draft['viewing_alert_id'] = null;
        $user->update(['telegram_alert_draft' => $draft]);

        $text = $locale === 'ar'
            ? '✅ تم حذف التنبيه'
            : '✅ Alert deleted';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::alertsKeyboard($locale),
        ]);

        return null;
    }

    private function promptAssetSearch(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['telegram_awaiting_input' => 'alert_asset_search']);

        $text = $locale === 'ar'
            ? "🔍 *بحث عن أصل*\n\nأدخل رمز أو اسم الأصل للبحث:"
            : "🔍 *Search Asset*\n\nEnter asset symbol or name to search:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
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
