<?php

namespace App\Telegram\Handlers\Settings;

use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Market Preferences settings.
 *
 * Callback patterns:
 * - set:markets - Show market settings
 * - set:markets:country - Show country selector
 * - set:markets:country:{id} - Select country
 * - set:markets:market:toggle:{id} - Toggle market
 * - set:markets:market:page:{n} - Paginate markets
 * - set:markets:sectors - Show sector selector
 * - set:markets:sector:toggle:{id} - Toggle sector
 * - set:markets:sector:page:{n} - Paginate sectors
 */
class MarketPreferencesHandler extends CallbackHandler
{
    protected string $match = '/^set:markets/';

    private const POPULAR_COUNTRY_CODES = ['EG', 'SA'];

    public function handle(): mixed
    {
        $callbackQuery = $this->update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError('User not found. Please /start first.');
        }

        $parts = explode(':', $data);
        $action = $parts[2] ?? null;
        $subAction = $parts[3] ?? null;
        $value = $parts[4] ?? null;
        $locale = $user->language ?? 'en';

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        // Show main markets settings view
        if ($action === null) {
            return $this->showMarketsSettings($chatId, $messageId, $user, $locale);
        }

        return match ($action) {
            'country' => $this->handleCountry($user, $subAction, $chatId, $messageId, $locale),
            'market' => $this->handleMarket($user, $subAction, $value, $chatId, $messageId, $locale),
            'sectors' => $this->showSectorSelector($chatId, $messageId, $user, $locale),
            'sector' => $this->handleSector($user, $subAction, $value, $chatId, $messageId, $locale),
            default => $this->showMarketsSettings($chatId, $messageId, $user, $locale),
        };
    }

    private function showMarketsSettings(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $country = $user->country;
        $countryName = $country
            ? ($locale === 'ar' ? $country->name_ar : $country->name_en)
            : ($locale === 'ar' ? 'غير محدد' : 'Not set');

        $marketsCount = $user->markets()->count();
        $sectorsCount = $user->sectors()->count();

        if ($locale === 'ar') {
            $text = <<<MSG
🌍 *تفضيلات الأسواق*
━━━━━━━━━━━━━━━━━━

🏳️ الدولة: {$countryName}
📊 الأسواق المختارة: {$marketsCount}
🏭 القطاعات المختارة: {$sectorsCount}
MSG;
        } else {
            $text = <<<MSG
🌍 *Market Preferences*
━━━━━━━━━━━━━━━━━━

🏳️ Country: {$countryName}
📊 Selected Markets: {$marketsCount}
🏭 Selected Sectors: {$sectorsCount}
MSG;
        }

        $keyboard = [
            [
                [
                    'text' => $locale === 'ar' ? '🏳️ تغيير الدولة' : '🏳️ Change Country',
                    'callback_data' => 'set:markets:country',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '📊 تعديل الأسواق' : '📊 Edit Markets',
                    'callback_data' => 'set:markets:market:page:1',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '🏭 تعديل القطاعات' : '🏭 Edit Sectors',
                    'callback_data' => 'set:markets:sectors',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                    'callback_data' => 'set:menu',
                ],
            ],
        ];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleCountry(User $user, ?string $countryId, int $chatId, int $messageId, string $locale): mixed
    {
        // Show country selector
        if ($countryId === null) {
            return $this->showCountrySelector($chatId, $messageId, $user, $locale);
        }

        // Select country
        $country = Country::find($countryId);
        if (! $country) {
            return $this->answerWithError('Country not found');
        }

        // If country changed, clear markets
        if ($user->country_id !== $country->id) {
            $user->markets()->detach();
        }

        $user->update(['country_id' => $country->id]);

        Log::info('Settings: Country updated via Telegram', [
            'user_id' => $user->id,
            'country_id' => $country->id,
        ]);

        $countryName = $locale === 'ar' ? $country->name_ar : $country->name_en;
        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم اختيار: {$countryName}"
                : "✓ Selected: {$countryName}",
            'show_alert' => false,
        ]);

        return $this->showMarketsSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function showCountrySelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "🏳️ *اختر دولتك:*"
            : "🏳️ *Select your country:*";

        // Use CASE WHEN for PostgreSQL-compatible ordering
        $orderCase = 'CASE ';
        foreach (self::POPULAR_COUNTRY_CODES as $index => $code) {
            $orderCase .= "WHEN code = '{$code}' THEN {$index} ";
        }
        $orderCase .= 'END';

        $popularCountries = Country::whereIn('code', self::POPULAR_COUNTRY_CODES)
            ->orderByRaw($orderCase)
            ->get();

        $flags = [
            'EG' => '🇪🇬',
            'SA' => '🇸🇦',
            'AE' => '🇦🇪',
            'KW' => '🇰🇼',
            'QA' => '🇶🇦',
            'BH' => '🇧🇭',
        ];

        $keyboard = [];

        // Countries in rows of 2
        $rows = $popularCountries->chunk(2);
        foreach ($rows as $row) {
            $btns = [];
            foreach ($row as $country) {
                $name = $locale === 'ar' ? $country->name_ar : $country->name_en;
                $flag = $flags[$country->code] ?? '🏳️';
                $isSelected = $user->country_id === $country->id;
                $btns[] = [
                    'text' => $isSelected ? "✓ {$flag} {$name}" : "{$flag} {$name}",
                    'callback_data' => "set:markets:country:{$country->id}",
                ];
            }
            $keyboard[] = $btns;
        }

        // Search button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '🔍 بحث عن دولة أخرى' : '🔍 Search other country',
            'callback_data' => 'set:markets:country:search',
        ]];

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:markets',
        ]];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleMarket(User $user, ?string $action, ?string $value, int $chatId, int $messageId, string $locale): mixed
    {
        if ($action === 'page') {
            $page = (int) ($value ?? 1);

            return $this->showMarketSelector($chatId, $messageId, $user, $locale, $page);
        }

        if ($action === 'toggle' && $value) {
            $market = Market::find($value);
            if (! $market) {
                return $this->answerWithError('Market not found');
            }

            $isSelected = $user->markets()->where('markets.id', $value)->exists();

            if ($isSelected) {
                $user->markets()->detach($value);
                $actionText = 'removed';
            } else {
                $user->markets()->attach($value);
                $actionText = 'added';
            }

            Log::info("Settings: Market {$actionText} via Telegram", [
                'user_id' => $user->id,
                'market_id' => $value,
            ]);

            $marketName = $locale === 'ar' ? ($market->name_ar ?: $market->name_en) : $market->name_en;
            $this->answerCallbackQuery([
                'text' => $actionText === 'added'
                    ? ($locale === 'ar' ? "✅ تمت إضافة: {$marketName}" : "✅ Added: {$marketName}")
                    : ($locale === 'ar' ? "❌ تمت إزالة: {$marketName}" : "❌ Removed: {$marketName}"),
                'show_alert' => false,
            ]);

            return $this->showMarketSelector($chatId, $messageId, $user->fresh(), $locale);
        }

        return $this->showMarketSelector($chatId, $messageId, $user, $locale);
    }

    private function showMarketSelector(int $chatId, int $messageId, User $user, string $locale, int $page = 1): mixed
    {
        if (! $user->country_id) {
            return $this->showCountrySelector($chatId, $messageId, $user, $locale);
        }

        $markets = Market::where('country_id', $user->country_id)->get();
        $selectedMarketIds = $user->markets()->pluck('markets.id')->toArray();

        $text = $locale === 'ar'
            ? "📊 *اختر الأسواق:*\n\nاضغط للتحديد/إلغاء التحديد"
            : "📊 *Select Markets:*\n\nTap to toggle selection";

        $keyboard = [];

        if ($markets->isEmpty()) {
            $keyboard[] = [[
                'text' => $locale === 'ar' ? '⚠️ لا توجد أسواق متاحة' : '⚠️ No markets available',
                'callback_data' => 'noop',
            ]];
        } else {
            // Paginate
            $perPage = 6;
            $totalPages = (int) ceil($markets->count() / $perPage);
            $offset = ($page - 1) * $perPage;
            $pageMarkets = $markets->slice($offset, $perPage);

            // Markets in rows of 2
            $rows = $pageMarkets->chunk(2);
            foreach ($rows as $row) {
                $btns = [];
                foreach ($row as $market) {
                    $name = $locale === 'ar' ? ($market->name_ar ?: $market->name_en) : $market->name_en;
                    $isSelected = in_array($market->id, $selectedMarketIds, true);
                    $btns[] = [
                        'text' => $isSelected ? "✅ {$name}" : "⬜ {$name}",
                        'callback_data' => "set:markets:market:toggle:{$market->id}",
                    ];
                }
                $keyboard[] = $btns;
            }

            // Pagination
            if ($totalPages > 1) {
                $navRow = [];
                if ($page > 1) {
                    $navRow[] = [
                        'text' => '◀️',
                        'callback_data' => 'set:markets:market:page:'.($page - 1),
                    ];
                }
                $navRow[] = [
                    'text' => "{$page}/{$totalPages}",
                    'callback_data' => 'noop',
                ];
                if ($page < $totalPages) {
                    $navRow[] = [
                        'text' => '▶️',
                        'callback_data' => 'set:markets:market:page:'.($page + 1),
                    ];
                }
                $keyboard[] = $navRow;
            }
        }

        // Selected count
        $selectedCount = count($selectedMarketIds);
        $keyboard[] = [[
            'text' => $locale === 'ar'
                ? "📊 المختار: {$selectedCount}"
                : "📊 Selected: {$selectedCount}",
            'callback_data' => 'noop',
        ]];

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:markets',
        ]];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function showSectorSelector(int $chatId, int $messageId, User $user, string $locale, int $page = 1): mixed
    {
        $sectors = Sector::all();
        $selectedSectorIds = $user->sectors()->pluck('sectors.id')->toArray();

        $text = $locale === 'ar'
            ? "🏭 *اختر القطاعات:*\n\nاضغط للتحديد/إلغاء التحديد"
            : "🏭 *Select Sectors:*\n\nTap to toggle selection";

        $keyboard = [];

        if ($sectors->isEmpty()) {
            $keyboard[] = [[
                'text' => $locale === 'ar' ? '⚠️ لا توجد قطاعات متاحة' : '⚠️ No sectors available',
                'callback_data' => 'noop',
            ]];
        } else {
            // Paginate
            $perPage = 6;
            $totalPages = (int) ceil($sectors->count() / $perPage);
            $offset = ($page - 1) * $perPage;
            $pageSectors = $sectors->slice($offset, $perPage);

            // Sectors in rows of 2
            $rows = $pageSectors->chunk(2);
            foreach ($rows as $row) {
                $btns = [];
                foreach ($row as $sector) {
                    $name = $locale === 'ar' ? ($sector->name_ar ?: $sector->name_en) : $sector->name_en;
                    $isSelected = in_array($sector->id, $selectedSectorIds, true);
                    $btns[] = [
                        'text' => $isSelected ? "✅ {$name}" : "⬜ {$name}",
                        'callback_data' => "set:markets:sector:toggle:{$sector->id}",
                    ];
                }
                $keyboard[] = $btns;
            }

            // Pagination
            if ($totalPages > 1) {
                $navRow = [];
                if ($page > 1) {
                    $navRow[] = [
                        'text' => '◀️',
                        'callback_data' => "set:markets:sector:page:".($page - 1),
                    ];
                }
                $navRow[] = [
                    'text' => "{$page}/{$totalPages}",
                    'callback_data' => 'noop',
                ];
                if ($page < $totalPages) {
                    $navRow[] = [
                        'text' => '▶️',
                        'callback_data' => "set:markets:sector:page:".($page + 1),
                    ];
                }
                $keyboard[] = $navRow;
            }
        }

        // Selected count
        $selectedCount = count($selectedSectorIds);
        $keyboard[] = [[
            'text' => $locale === 'ar'
                ? "🏭 المختار: {$selectedCount}"
                : "🏭 Selected: {$selectedCount}",
            'callback_data' => 'noop',
        ]];

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:markets',
        ]];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleSector(User $user, ?string $action, ?string $value, int $chatId, int $messageId, string $locale): mixed
    {
        if ($action === 'page') {
            $page = (int) ($value ?? 1);

            return $this->showSectorSelector($chatId, $messageId, $user, $locale, $page);
        }

        if ($action === 'toggle' && $value) {
            $sector = Sector::find($value);
            if (! $sector) {
                return $this->answerWithError('Sector not found');
            }

            $isSelected = $user->sectors()->where('sectors.id', $value)->exists();

            if ($isSelected) {
                $user->sectors()->detach($value);
                $actionText = 'removed';
            } else {
                $user->sectors()->attach($value);
                $actionText = 'added';
            }

            Log::info("Settings: Sector {$actionText} via Telegram", [
                'user_id' => $user->id,
                'sector_id' => $value,
            ]);

            $sectorName = $locale === 'ar' ? ($sector->name_ar ?: $sector->name_en) : $sector->name_en;
            $this->answerCallbackQuery([
                'text' => $actionText === 'added'
                    ? ($locale === 'ar' ? "✅ تمت إضافة: {$sectorName}" : "✅ Added: {$sectorName}")
                    : ($locale === 'ar' ? "❌ تمت إزالة: {$sectorName}" : "❌ Removed: {$sectorName}"),
                'show_alert' => false,
            ]);

            return $this->showSectorSelector($chatId, $messageId, $user->fresh(), $locale);
        }

        return $this->showSectorSelector($chatId, $messageId, $user, $locale);
    }

    private function answerWithError(string $message): null
    {
        $this->answerCallbackQuery([
            'text' => $message,
            'show_alert' => true,
        ]);

        return null;
    }
}
