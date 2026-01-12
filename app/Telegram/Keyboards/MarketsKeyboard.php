<?php

namespace App\Telegram\Keyboards;

class MarketsKeyboard
{
    /**
     * Markets settings keyboard.
     */
    public static function menu(string $locale): array
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
}
