<?php

namespace App\Telegram\Handlers;

use App\Models\Asset;
use App\Models\Country;
use App\Models\User;
use App\Telegram\Services\AlertKeyboardBuilder;
use App\Telegram\Services\DefaultKeyboardBuilder;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\UpdateHandler;

/**
 * Handler for text input responses when user is awaiting input.
 *
 * This handles:
 * - Name change (telegram_awaiting_input = 'name')
 * - Country search (telegram_awaiting_input = 'country_search')
 * - Alert asset search (telegram_awaiting_input = 'alert_asset_search')
 * - Alert target price (telegram_awaiting_input = 'alert_target_price')
 * - Alert percentage (telegram_awaiting_input = 'alert_percentage')
 */
class TextInputHandler extends UpdateHandler
{
    public function trigger(): bool
    {
        // Must be a text message (not command) with no entities
        if (! isset($this->update->message?->text)) {
            return false;
        }

        $text = $this->update->message->text;

        // Ignore commands
        if (str_starts_with($text, '/')) {
            return false;
        }

        // Check if user is awaiting input
        $telegramId = (string) $this->update->message->from->id;
        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user || ! $user->telegram_awaiting_input) {
            return false;
        }

        return true;
    }

    public function handle(): mixed
    {
        $message = $this->update->message;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;
        $text = trim($message->text);

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return null;
        }

        $awaitingInput = $user->telegram_awaiting_input;
        $locale = $user->language ?? 'en';

        // Clear awaiting input state first
        $user->update(['telegram_awaiting_input' => null]);

        return match ($awaitingInput) {
            'name' => $this->handleNameInput($user, $text, $chatId, $locale),
            'country_search' => $this->handleCountrySearch($user, $text, $chatId, $locale),
            'alert_asset_search' => $this->handleAlertAssetSearch($user, $text, $chatId, $locale),
            'alert_target_price' => $this->handleAlertTargetPrice($user, $text, $chatId, $locale),
            'alert_percentage' => $this->handleAlertPercentage($user, $text, $chatId, $locale),
            default => null,
        };
    }

    private function handleNameInput(User $user, string $name, int $chatId, string $locale): mixed
    {
        // Validate name
        $name = trim($name);

        if (mb_strlen($name) < 2) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ الاسم قصير جدا. يجب أن يكون حرفين على الأقل.'
                    : '❌ Name is too short. Must be at least 2 characters.',
                'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
            ]);

            return null;
        }

        if (mb_strlen($name) > 100) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ الاسم طويل جدا. يجب ألا يتجاوز 100 حرف.'
                    : '❌ Name is too long. Must be 100 characters or less.',
                'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
            ]);

            return null;
        }

        $user->update(['name' => $name]);

        Log::info('Name updated via Telegram', [
            'user_id' => $user->id,
            'name' => $name,
        ]);

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $locale === 'ar'
                ? "✅ تم تحديث الاسم إلى: {$name}"
                : "✅ Name updated to: {$name}",
            'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
        ]);

        return null;
    }

    private function handleCountrySearch(User $user, string $query, int $chatId, string $locale): mixed
    {
        // Search for country by name
        $query = trim($query);
        $builder = new OnboardingKeyboardBuilder;

        if (mb_strlen($query) < 2) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ يرجى إدخال حرفين على الأقل للبحث.'
                    : '❌ Please enter at least 2 characters to search.',
                'reply_markup' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
            ]);

            return null;
        }

        // Search in both English and Arabic names
        $countries = Country::where('name_en', 'LIKE', "%{$query}%")
            ->orWhere('name_ar', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get();

        if ($countries->isEmpty()) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? "❌ لم يتم العثور على دول بهذا الاسم: {$query}"
                    : "❌ No countries found matching: {$query}",
                'reply_markup' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
            ]);

            return null;
        }

        // Build country search results keyboard
        $keyboard = [];
        foreach ($countries as $country) {
            $name = $locale === 'ar' ? $country->name_ar : $country->name_en;
            $keyboard[] = [['text' => "🏳️ {$name}"]];
        }
        $keyboard[] = [['text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back']];

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $locale === 'ar'
                ? "🔍 نتائج البحث عن: {$query}"
                : "🔍 Search results for: {$query}",
            'reply_markup' => [
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ],
        ]);

        return null;
    }

    private function handleAlertAssetSearch(User $user, string $query, int $chatId, string $locale): mixed
    {
        $query = trim($query);
        $builder = new AlertKeyboardBuilder;

        if (mb_strlen($query) < 1) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ يرجى إدخال رمز أو اسم الأصل.'
                    : '❌ Please enter an asset symbol or name.',
            ]);

            return null;
        }

        // Search assets by symbol or name
        $assets = Asset::where('symbol', 'LIKE', "%{$query}%")
            ->orWhere('name_en', 'LIKE', "%{$query}%")
            ->orWhere('name_ar', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get();

        if ($assets->isEmpty()) {
            $text = $locale === 'ar'
                ? "❌ لم يتم العثور على أصول بهذا الاسم: {$query}\n\nحاول مرة أخرى أو اضغط إلغاء."
                : "❌ No assets found matching: {$query}\n\nTry again or tap Cancel.";

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => [
                    'inline_keyboard' => $builder->buildCancelButton('alert:create', $locale),
                ],
            ]);

            // Keep waiting for input
            $user->update(['telegram_awaiting_input' => 'alert_asset_search']);

            return null;
        }

        // Show search results
        $keyboard = $builder->buildAssetSearchResults($assets, $locale);

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $locale === 'ar'
                ? "🔍 نتائج البحث عن: {$query}"
                : "🔍 Search results for: {$query}",
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        return null;
    }

    private function handleAlertTargetPrice(User $user, string $input, int $chatId, string $locale): mixed
    {
        $input = trim($input);
        $builder = new AlertKeyboardBuilder;

        // Parse the price value
        $price = $this->parseNumericInput($input);

        if ($price === null || $price <= 0) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ يرجى إدخال سعر صحيح (مثال: 150.50)'
                    : '❌ Please enter a valid price (e.g., 150.50)',
                'reply_markup' => [
                    'inline_keyboard' => $builder->buildCancelButton('alert:create', $locale),
                ],
            ]);

            // Keep waiting for input
            $user->update(['telegram_awaiting_input' => 'alert_target_price']);

            return null;
        }

        // Update draft with target price
        $draft = $user->telegram_alert_draft ?? [];
        $draft['step'] = 'direction';
        $draft['parameters'] = $draft['parameters'] ?? [];
        $draft['parameters']['target_price'] = $price;
        $user->update(['telegram_alert_draft' => $draft]);

        // Show direction selector
        $symbol = $draft['asset_symbol'] ?? 'N/A';
        $formattedPrice = number_format($price, 2);

        $text = $locale === 'ar'
            ? "🎯 *تنبيه سعر لـ {$symbol}*\n\nالسعر المستهدف: {$formattedPrice}\n\nمتى يتم تنبيهك؟"
            : "🎯 *Price Alert for {$symbol}*\n\nTarget price: {$formattedPrice}\n\nWhen should we alert you?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildDirectionSelector($price, $locale),
            ],
        ]);

        return null;
    }

    private function handleAlertPercentage(User $user, string $input, int $chatId, string $locale): mixed
    {
        $input = trim($input);
        $builder = new AlertKeyboardBuilder;

        // Parse the percentage value
        $percentage = $this->parseNumericInput($input);

        if ($percentage === null || $percentage <= 0 || $percentage > 100) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ يرجى إدخال نسبة صحيحة بين 0.1 و 100 (مثال: 5)'
                    : '❌ Please enter a valid percentage between 0.1 and 100 (e.g., 5)',
                'reply_markup' => [
                    'inline_keyboard' => $builder->buildCancelButton('alert:create', $locale),
                ],
            ]);

            // Keep waiting for input
            $user->update(['telegram_awaiting_input' => 'alert_percentage']);

            return null;
        }

        // Update draft with percentage
        $draft = $user->telegram_alert_draft ?? [];
        $draft['step'] = 'direction';
        $draft['parameters'] = $draft['parameters'] ?? [];
        $draft['parameters']['threshold_percent'] = $percentage;
        $user->update(['telegram_alert_draft' => $draft]);

        // Show direction selector
        $symbol = $draft['asset_symbol'] ?? 'N/A';

        $text = $locale === 'ar'
            ? "📊 *تنبيه تغير يومي لـ {$symbol}*\n\nنسبة التغير: {$percentage}%\n\nمتى يتم تنبيهك؟"
            : "📊 *Daily Change Alert for {$symbol}*\n\nChange threshold: {$percentage}%\n\nWhen should we alert you?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildPercentageDirectionSelector($percentage, $locale),
            ],
        ]);

        return null;
    }

    private function parseNumericInput(string $input): ?float
    {
        // Remove common formatting characters
        $input = str_replace([',', ' ', '$', '£', '€', '٪', '%'], '', $input);

        // Convert Arabic numerals to Western
        $arabicNumerals = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $westernNumerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $input = str_replace($arabicNumerals, $westernNumerals, $input);

        // Validate and parse
        if (! is_numeric($input)) {
            return null;
        }

        return (float) $input;
    }
}
