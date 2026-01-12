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
        $notSet = $locale === 'ar' ? 'غير محدد' : 'Not set';
        $countryName = $notSet;
        if ($user?->country) {
            $name = $locale === 'ar' ? $user->country->name_ar : $user->country->name_en;
            // Escape underscores for Markdown
            $countryName = str_replace('_', '\\_', $name);
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
        $none = $locale === 'ar' ? 'لا يوجد' : 'None';
        $marketNames = $none;
        if ($user) {
            $markets = $user->markets()->get();
            if ($markets->isNotEmpty()) {
                $names = $markets->map(function ($m) use ($locale) {
                    $name = $locale === 'ar' ? ($m->name_ar ?: $m->name_en) : $m->name_en;

                    // Escape underscores for Markdown
                    return str_replace('_', '\\_', $name);
                });
                $marketNames = $names->join(', ');
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
        $none = $locale === 'ar' ? 'لا يوجد' : 'None';
        $sectorNames = $none;
        if ($user) {
            $sectors = $user->sectors()->get();
            if ($sectors->isNotEmpty()) {
                $names = $sectors->map(function ($s) use ($locale) {
                    $name = $locale === 'ar' ? ($s->name_ar ?: $s->name_en) : $s->name_en;

                    // Escape underscores for Markdown
                    return str_replace('_', '\\_', $name);
                });
                $sectorNames = $names->join(', ');
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
