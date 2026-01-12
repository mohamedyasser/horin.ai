<?php

namespace App\Telegram\Handlers\Buttons;

class ButtonRegistry
{
    /**
     * Button text mappings to action keys (both English and Arabic).
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

        // Back button
        '◀️ Back' => 'back',
        '◀️ رجوع' => 'back',

        // Language selection buttons
        '🇬🇧 English' => 'set_language_en',
        '🇸🇦 العربية' => 'set_language_ar',

        // Phone share button
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

    /**
     * Action key to handler class and method mapping.
     *
     * @var array<string, array{class-string, string}>
     */
    private const ACTION_HANDLERS = [
        // Navigation actions
        'back' => [NavigationButtonHandler::class, 'goBackToMainMenu'],
        'cancel_input' => [NavigationButtonHandler::class, 'cancelInput'],
        'set_language_en' => [NavigationButtonHandler::class, 'setLanguageEn'],
        'set_language_ar' => [NavigationButtonHandler::class, 'setLanguageAr'],
        'phone_share_hint' => [NavigationButtonHandler::class, 'showPhoneShareHint'],
        'help' => [NavigationButtonHandler::class, 'triggerHelpCommand'],
        'onboarding' => [NavigationButtonHandler::class, 'triggerOnboardingCommand'],

        // Settings actions
        'settings' => [SettingsButtonHandler::class, 'showSettings'],
        'settings_profile' => [SettingsButtonHandler::class, 'showProfile'],
        'settings_trading' => [SettingsButtonHandler::class, 'showTrading'],
        'settings_markets' => [SettingsButtonHandler::class, 'showMarkets'],
        'settings_alerts' => [SettingsButtonHandler::class, 'showAlertSettings'],
        'settings_language' => [SettingsButtonHandler::class, 'showLanguage'],
        'profile_change_name' => [SettingsButtonHandler::class, 'promptNameChange'],
        'alert_prefs_channels' => [SettingsButtonHandler::class, 'showChannelsSelector'],
        'alert_prefs_limits' => [SettingsButtonHandler::class, 'showLimitsSelector'],
        'limit_hour_decrease' => [SettingsButtonHandler::class, 'adjustLimitHourDecrease'],
        'limit_hour_increase' => [SettingsButtonHandler::class, 'adjustLimitHourIncrease'],
        'limit_day_decrease' => [SettingsButtonHandler::class, 'adjustLimitDayDecrease'],
        'limit_day_increase' => [SettingsButtonHandler::class, 'adjustLimitDayIncrease'],

        // Trading actions
        'trading_experience' => [TradingButtonHandler::class, 'showExperienceSelector'],
        'trading_risk' => [TradingButtonHandler::class, 'showRiskSelector'],
        'trading_goal' => [TradingButtonHandler::class, 'showGoalSelector'],
        'trading_style' => [TradingButtonHandler::class, 'showStyleSelector'],
        'set_experience_beginner' => [TradingButtonHandler::class, 'setExperienceBeginner'],
        'set_experience_intermediate' => [TradingButtonHandler::class, 'setExperienceIntermediate'],
        'set_experience_advanced' => [TradingButtonHandler::class, 'setExperienceAdvanced'],
        'set_risk_conservative' => [TradingButtonHandler::class, 'setRiskConservative'],
        'set_risk_moderate' => [TradingButtonHandler::class, 'setRiskModerate'],
        'set_risk_aggressive' => [TradingButtonHandler::class, 'setRiskAggressive'],
        'set_goal_capital_growth' => [TradingButtonHandler::class, 'setGoalCapitalGrowth'],
        'set_goal_fixed_income' => [TradingButtonHandler::class, 'setGoalFixedIncome'],
        'set_goal_risk_reduction' => [TradingButtonHandler::class, 'setGoalRiskReduction'],
        'set_goal_short_term' => [TradingButtonHandler::class, 'setGoalShortTerm'],
        'set_style_day_trading' => [TradingButtonHandler::class, 'setStyleDayTrading'],
        'set_style_swing_trading' => [TradingButtonHandler::class, 'setStyleSwingTrading'],
        'set_style_position_trading' => [TradingButtonHandler::class, 'setStylePositionTrading'],
        'set_style_scalping' => [TradingButtonHandler::class, 'setStyleScalping'],

        // Markets actions
        'markets_country' => [MarketsButtonHandler::class, 'showCountryInfo'],
        'markets_markets' => [MarketsButtonHandler::class, 'showMarketsInfo'],
        'markets_sectors' => [MarketsButtonHandler::class, 'showSectorsInfo'],

        // Alerts actions
        'alerts' => [AlertsButtonHandler::class, 'showAlerts'],
        'new_alert' => [AlertsButtonHandler::class, 'createNewAlert'],
        'alerts_list' => [AlertsButtonHandler::class, 'showAlertsList'],
        'alerts_history' => [AlertsButtonHandler::class, 'showAlertsHistory'],
        'alert_type_price' => [AlertsButtonHandler::class, 'selectAlertTypePrice'],
        'alert_type_signal' => [AlertsButtonHandler::class, 'selectAlertTypeSignal'],
        'alert_type_prediction' => [AlertsButtonHandler::class, 'selectAlertTypePrediction'],
        'alert_trigger_target_price' => [AlertsButtonHandler::class, 'setTriggerTargetPrice'],
        'alert_trigger_daily_change' => [AlertsButtonHandler::class, 'setTriggerDailyChange'],
        'alert_trigger_breakout' => [AlertsButtonHandler::class, 'setTriggerBreakout'],
        'alert_direction_above' => [AlertsButtonHandler::class, 'setDirectionAbove'],
        'alert_direction_below' => [AlertsButtonHandler::class, 'setDirectionBelow'],
        'alert_direction_both' => [AlertsButtonHandler::class, 'setDirectionBoth'],
        'alert_confirm_create' => [AlertsButtonHandler::class, 'confirmCreateAlert'],
        'alert_snooze' => [AlertsButtonHandler::class, 'showSnoozeOptions'],
        'alert_unsnooze' => [AlertsButtonHandler::class, 'unsnoozeAlert'],
        'alert_pause' => [AlertsButtonHandler::class, 'toggleAlertStatus'],
        'alert_resume' => [AlertsButtonHandler::class, 'toggleAlertStatus'],
        'alert_delete' => [AlertsButtonHandler::class, 'showDeleteConfirmation'],
        'alert_delete_confirm' => [AlertsButtonHandler::class, 'executeDeleteAlert'],
        'snooze_1h' => [AlertsButtonHandler::class, 'applySnooze1h'],
        'snooze_4h' => [AlertsButtonHandler::class, 'applySnooze4h'],
        'snooze_1d' => [AlertsButtonHandler::class, 'applySnooze1d'],
        'snooze_market_close' => [AlertsButtonHandler::class, 'applySnoozeMarketClose'],
        'alert_search_asset' => [AlertsButtonHandler::class, 'promptAssetSearch'],
    ];

    /**
     * Resolve a button text to its handler class and method.
     *
     * @return array{class-string, string, string}|null [handler_class, method, action_key]
     */
    public static function resolve(string $buttonText): ?array
    {
        $action = self::BUTTON_MAPPINGS[$buttonText] ?? null;

        if (! $action) {
            return null;
        }

        $handler = self::ACTION_HANDLERS[$action] ?? null;

        if (! $handler) {
            return null;
        }

        return [$handler[0], $handler[1], $action];
    }

    /**
     * Get all button mappings.
     *
     * @return array<string, string>
     */
    public static function getButtonMappings(): array
    {
        return self::BUTTON_MAPPINGS;
    }

    /**
     * Check if a button text is registered.
     */
    public static function hasButton(string $buttonText): bool
    {
        return isset(self::BUTTON_MAPPINGS[$buttonText]);
    }
}
