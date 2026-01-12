<?php

namespace App\Telegram\Handlers\Settings;

use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use App\Models\User;
use App\Telegram\Services\SettingsKeyboardBuilder;
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
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'ما الذي تريد تغييره؟'
            : 'What would you like to change?';

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildMarketPreferencesMenu($user, $locale),
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
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'اختر دولتك:'
            : 'Select your country:';

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildCountrySelector($user->country_id, $locale),
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

        $builder = new SettingsKeyboardBuilder;
        $selectedMarketIds = $user->markets()->pluck('markets.id')->toArray();

        $text = $locale === 'ar'
            ? 'اختر الأسواق:'
            : 'Select markets:';

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildMarketSelector($user->country_id, $selectedMarketIds, $locale, $page),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function showSectorSelector(int $chatId, int $messageId, User $user, string $locale, int $page = 1): mixed
    {
        $builder = new SettingsKeyboardBuilder;
        $selectedSectorIds = $user->sectors()->pluck('sectors.id')->toArray();

        $text = $locale === 'ar'
            ? 'اختر القطاعات:'
            : 'Select sectors:';

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildSectorSelector($selectedSectorIds, $locale, $page),
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
