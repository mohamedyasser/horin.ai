<?php

namespace App\Telegram\Keyboards;

class TradingKeyboard
{
    /**
     * Trading settings keyboard.
     */
    public static function menu(string $locale): array
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
}
