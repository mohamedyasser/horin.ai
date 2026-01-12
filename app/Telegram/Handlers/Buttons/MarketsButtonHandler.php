<?php

namespace App\Telegram\Handlers\Buttons;

use App\Models\User;
use App\Telegram\Keyboards\MarketsKeyboard;

class MarketsButtonHandler extends AbstractButtonHandler
{
    public static function supportedActions(): array
    {
        return [
            'markets_country',
            'markets_markets',
            'markets_sectors',
        ];
    }

    public function showCountryInfo(int $chatId, ?User $user, string $locale): mixed
    {
        $countryName = 'Not set';
        if ($user?->country) {
            $countryName = $locale === 'ar' ? $user->country->name_ar : $user->country->name_en;
        }

        $text = $locale === 'ar'
            ? "🌍 *الدولة*\n\nالدولة الحالية: {$countryName}\n\n⚠️ لتغيير الدولة، يرجى استخدام التطبيق."
            : "🌍 *Country*\n\nCurrent country: {$countryName}\n\n⚠️ To change country, please use the app.";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => MarketsKeyboard::menu($locale),
        ]);
    }

    public function showMarketsInfo(int $chatId, ?User $user, string $locale): mixed
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

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => MarketsKeyboard::menu($locale),
        ]);
    }

    public function showSectorsInfo(int $chatId, ?User $user, string $locale): mixed
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

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => MarketsKeyboard::menu($locale),
        ]);
    }
}
