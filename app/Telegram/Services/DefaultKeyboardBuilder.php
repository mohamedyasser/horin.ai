<?php

namespace App\Telegram\Services;

use App\Models\User;

class DefaultKeyboardBuilder
{
    /**
     * Get the default keyboard for a user based on their state.
     */
    public static function forUser(?User $user, ?string $locale = null): array
    {
        // Truly new user (no record) - show language selection first
        if (! $user) {
            return self::languageKeyboard();
        }

        // User exists but not phone verified - show phone verification
        if (! $user->hasVerifiedPhone()) {
            return self::phoneVerificationKeyboard($user->language ?? $locale ?? 'en');
        }

        // User not onboarded - show onboarding keyboard
        if (! $user->hasCompletedOnboarding()) {
            return self::onboardingKeyboard($user->language ?? 'en');
        }

        // Fully verified user - show main menu
        return self::mainMenuKeyboard($user->language ?? 'en');
    }

    /**
     * Phone verification keyboard - for unverified users.
     */
    public static function phoneVerificationKeyboard(string $locale): array
    {
        $buttonText = $locale === 'ar' ? '📱 مشاركة رقم الهاتف' : '📱 Share Phone Number';

        return [
            'keyboard' => [[
                [
                    'text' => $buttonText,
                    'request_contact' => true,
                ],
            ]],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Onboarding keyboard - for users in onboarding.
     */
    public static function onboardingKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '▶️ متابعة الإعداد']],
                    [['text' => '❓ مساعدة']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '▶️ Continue Setup']],
                [['text' => '❓ Help']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Main menu keyboard - for fully verified users.
     */
    public static function mainMenuKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📋 التنبيهات'], ['text' => '➕ تنبيه جديد']],
                    [['text' => '⚙️ الإعدادات'], ['text' => '❓ مساعدة']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📋 Alerts'], ['text' => '➕ New Alert']],
                [['text' => '⚙️ Settings'], ['text' => '❓ Help']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Get the "I don't understand" message with default keyboard.
     * For truly new users (no record), show language selection first.
     */
    public static function getUnknownInputResponse(?User $user, ?string $locale = null): array
    {
        // Truly new user - show language selection first
        if (! $user) {
            return [
                'text' => "👋 Welcome! / مرحباً!\n\nPlease select your language:\nيرجى اختيار لغتك:",
                'reply_markup' => self::languageKeyboard(),
            ];
        }

        $locale = $locale ?? $user->language ?? 'en';

        $message = $locale === 'ar'
            ? 'عذراً، لم أفهم ذلك. يرجى اختيار أحد الخيارات من القائمة أدناه:'
            : "Sorry, I didn't understand that. Please choose from the options below:";

        return [
            'text' => $message,
            'reply_markup' => self::forUser($user, $locale),
        ];
    }

    /**
     * Settings menu keyboard.
     */
    public static function settingsKeyboard(string $locale): array
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
     * Alerts menu keyboard.
     */
    public static function alertsKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📋 تنبيهاتي'], ['text' => '➕ تنبيه جديد']],
                    [['text' => '📜 السجل']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📋 My Alerts'], ['text' => '➕ New Alert']],
                [['text' => '📜 History']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Alert type selection keyboard - for creating alerts.
     */
    public static function alertTypeKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '💰 تنبيه السعر']],
                    [['text' => '📈 تنبيه إشارة']],
                    [['text' => '🔮 تنبيه توقع']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '💰 Price Alert']],
                [['text' => '📈 Signal Alert']],
                [['text' => '🔮 Prediction Alert']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Language selection keyboard.
     */
    public static function languageKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '🇬🇧 English'], ['text' => '🇸🇦 العربية']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Remove keyboard (for special cases).
     */
    public static function removeKeyboard(): array
    {
        return [
            'remove_keyboard' => true,
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
     * Trading settings keyboard.
     */
    public static function tradingKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📈 مستوى الخبرة'], ['text' => '⚠️ مستوى المخاطرة']],
                    [['text' => '🎯 الهدف الاستثماري'], ['text' => '📊 أسلوب التداول']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📈 Experience Level'], ['text' => '⚠️ Risk Level']],
                [['text' => '🎯 Investment Goal'], ['text' => '📊 Trading Style']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Experience level selection keyboard.
     */
    public static function experienceKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '🌱 مبتدئ']],
                    [['text' => '📊 متوسط']],
                    [['text' => '🎓 متقدم']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '🌱 Beginner']],
                [['text' => '📊 Intermediate']],
                [['text' => '🎓 Advanced']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Risk level selection keyboard.
     */
    public static function riskKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '🛡️ محافظ']],
                    [['text' => '⚖️ معتدل']],
                    [['text' => '🔥 مغامر']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '🛡️ Conservative']],
                [['text' => '⚖️ Moderate']],
                [['text' => '🔥 Aggressive']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Investment goal selection keyboard.
     */
    public static function goalKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📈 نمو رأس المال']],
                    [['text' => '💵 دخل ثابت']],
                    [['text' => '🛡️ تقليل المخاطر']],
                    [['text' => '⚡ مضاربة قصيرة']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📈 Capital Growth']],
                [['text' => '💵 Fixed Income']],
                [['text' => '🛡️ Risk Reduction']],
                [['text' => '⚡ Short-term Speculation']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Trading style selection keyboard.
     */
    public static function styleKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📅 تداول يومي']],
                    [['text' => '📊 تداول متأرجح']],
                    [['text' => '📈 تداول مراكز']],
                    [['text' => '⚡ سكالبينج']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📅 Day Trading']],
                [['text' => '📊 Swing Trading']],
                [['text' => '📈 Position Trading']],
                [['text' => '⚡ Scalping']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Markets settings keyboard.
     */
    public static function marketsSettingsKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '🌍 الدولة']],
                    [['text' => '🏛️ الأسواق']],
                    [['text' => '📂 القطاعات']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '🌍 Country']],
                [['text' => '🏛️ Markets']],
                [['text' => '📂 Sectors']],
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

    /**
     * Cancel keyboard - for text input prompts.
     */
    public static function cancelKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '❌ إلغاء']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '❌ Cancel']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Alert trigger type selection keyboard.
     */
    public static function alertTriggerKeyboard(string $alertType, string $locale): array
    {
        if ($alertType === 'signal') {
            // Signal alerts go directly to direction
            return self::alertDirectionKeyboard($locale);
        }

        if ($alertType === 'prediction') {
            // Prediction alerts go directly to direction
            return self::alertDirectionKeyboard($locale);
        }

        // Price alerts have trigger type selection
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '🎯 سعر مستهدف']],
                    [['text' => '📊 تغير يومي']],
                    [['text' => '📈 اختراق سعر']],
                    [['text' => '❌ إلغاء']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '🎯 Target Price']],
                [['text' => '📊 Daily Change']],
                [['text' => '📈 Price Breakout']],
                [['text' => '❌ Cancel']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Alert direction selection keyboard.
     */
    public static function alertDirectionKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '⬆️ أعلى من']],
                    [['text' => '⬇️ أقل من']],
                    [['text' => '↕️ أي اتجاه']],
                    [['text' => '❌ إلغاء']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '⬆️ Above']],
                [['text' => '⬇️ Below']],
                [['text' => '↕️ Either Direction']],
                [['text' => '❌ Cancel']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Alert confirmation keyboard.
     */
    public static function alertConfirmKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '✅ تأكيد الإنشاء']],
                    [['text' => '❌ إلغاء']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '✅ Confirm Create']],
                [['text' => '❌ Cancel']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Alert success keyboard - after creating alert.
     */
    public static function alertSuccessKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '➕ تنبيه جديد']],
                    [['text' => '📋 تنبيهاتي']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '➕ New Alert']],
                [['text' => '📋 My Alerts']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Alert detail actions keyboard.
     */
    public static function alertActionsKeyboard(string $status, bool $isSnoozed, string $locale): array
    {
        $pauseText = $status === 'active'
            ? ($locale === 'ar' ? '⏸️ إيقاف مؤقت' : '⏸️ Pause')
            : ($locale === 'ar' ? '▶️ تفعيل' : '▶️ Resume');

        $snoozeText = $isSnoozed
            ? ($locale === 'ar' ? '⏰ إلغاء التأجيل' : '⏰ Unsnooze')
            : ($locale === 'ar' ? '😴 تأجيل' : '😴 Snooze');

        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => $snoozeText], ['text' => $pauseText]],
                    [['text' => '🗑️ حذف']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => $snoozeText], ['text' => $pauseText]],
                [['text' => '🗑️ Delete']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Snooze options keyboard.
     */
    public static function snoozeOptionsKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '⏰ ساعة واحدة'], ['text' => '⏰ 4 ساعات']],
                    [['text' => '📅 يوم واحد']],
                    [['text' => '🔔 حتى إغلاق السوق']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '⏰ 1 Hour'], ['text' => '⏰ 4 Hours']],
                [['text' => '📅 1 Day']],
                [['text' => '🔔 Until Market Close']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Delete confirmation keyboard.
     */
    public static function deleteConfirmKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '🗑️ نعم، احذف']],
                    [['text' => '◀️ لا، رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '🗑️ Yes, Delete']],
                [['text' => '◀️ No, Go Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Asset search prompt keyboard.
     */
    public static function assetSearchKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '🔍 بحث عن أصل']],
                    [['text' => '❌ إلغاء']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '🔍 Search Asset']],
                [['text' => '❌ Cancel']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }
}
