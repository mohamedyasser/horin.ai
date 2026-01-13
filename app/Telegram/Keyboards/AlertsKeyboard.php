<?php

namespace App\Telegram\Keyboards;

class AlertsKeyboard
{
    /**
     * Alerts menu keyboard.
     */
    public static function menu(string $locale): array
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
                    [['text' => '💰 تنبيه السعر'], ['text' => '📈 تنبيه إشارة']],
                    [['text' => '🔮 تنبيه توقع'], ['text' => '⚠️ تنبيه شذوذ']],
                    [['text' => '📊 تنبيه نمط'], ['text' => '💡 تنبيه توصية']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '💰 Price Alert'], ['text' => '📈 Signal Alert']],
                [['text' => '🔮 Prediction Alert'], ['text' => '⚠️ Anomaly Alert']],
                [['text' => '📊 Pattern Alert'], ['text' => '💡 Recommendation']],
                [['text' => '◀️ Back']],
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
        // AI-based alerts skip to direction selection
        if (in_array($alertType, ['signal', 'prediction', 'anomaly', 'pattern', 'recommendation'])) {
            return self::alertDirectionKeyboard($locale);
        }

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
     * Anomaly type selection keyboard.
     */
    public static function anomalyTypeKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📊 حجم غير طبيعي'], ['text' => '💰 سعر غير طبيعي']],
                    [['text' => '📈 تقلب غير طبيعي'], ['text' => '🔄 جميع الأنواع']],
                    [['text' => '❌ إلغاء']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📊 Volume Anomaly'], ['text' => '💰 Price Anomaly']],
                [['text' => '📈 Volatility Anomaly'], ['text' => '🔄 All Types']],
                [['text' => '❌ Cancel']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Pattern type selection keyboard.
     */
    public static function patternTypeKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📈 رأس وكتفين'], ['text' => '📊 قمة/قاع مزدوج']],
                    [['text' => '🔺 مثلث'], ['text' => '📉 علم/راية']],
                    [['text' => '🔄 جميع الأنماط']],
                    [['text' => '❌ إلغاء']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📈 Head & Shoulders'], ['text' => '📊 Double Top/Bottom']],
                [['text' => '🔺 Triangle'], ['text' => '📉 Flag/Pennant']],
                [['text' => '🔄 All Patterns']],
                [['text' => '❌ Cancel']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Recommendation type selection keyboard.
     */
    public static function recommendationTypeKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '🚀 شراء قوي'], ['text' => '📈 شراء']],
                    [['text' => '📉 بيع'], ['text' => '🔻 بيع قوي']],
                    [['text' => '🔄 أي تغيير']],
                    [['text' => '❌ إلغاء']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '🚀 Strong Buy'], ['text' => '📈 Buy']],
                [['text' => '📉 Sell'], ['text' => '🔻 Strong Sell']],
                [['text' => '🔄 Any Change']],
                [['text' => '❌ Cancel']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Confidence level selection keyboard.
     */
    public static function confidenceLevelKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '🎯 عالي (80%+)'], ['text' => '📊 متوسط (60%+)']],
                    [['text' => '📉 منخفض (40%+)']],
                    [['text' => '❌ إلغاء']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '🎯 High (80%+)'], ['text' => '📊 Medium (60%+)']],
                [['text' => '📉 Low (40%+)']],
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
