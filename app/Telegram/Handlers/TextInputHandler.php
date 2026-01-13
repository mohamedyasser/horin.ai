<?php

namespace App\Telegram\Handlers;

use App\Models\Asset;
use App\Models\Country;
use App\Models\User;
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
            'alert_asset_select' => $this->handleAlertAssetSelect($user, $text, $chatId, $locale),
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
            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ الاسم قصير جدا. يجب أن يكون حرفين على الأقل.'
                    : '❌ Name is too short. Must be at least 2 characters.',
                'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
            ]);
        }

        if (mb_strlen($name) > 100) {
            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ الاسم طويل جدا. يجب ألا يتجاوز 100 حرف.'
                    : '❌ Name is too long. Must be 100 characters or less.',
                'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
            ]);
        }

        $user->update(['name' => $name]);

        Log::info('Name updated via Telegram', [
            'user_id' => $user->id,
            'name' => $name,
        ]);

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $locale === 'ar'
                ? "✅ تم تحديث الاسم إلى: {$name}"
                : "✅ Name updated to: {$name}",
            'reply_markup' => DefaultKeyboardBuilder::settingsKeyboard($locale),
        ]);
    }

    private function handleCountrySearch(User $user, string $query, int $chatId, string $locale): mixed
    {
        // Search for country by name
        $query = trim($query);
        $builder = new OnboardingKeyboardBuilder;

        if (mb_strlen($query) < 2) {
            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ يرجى إدخال حرفين على الأقل للبحث.'
                    : '❌ Please enter at least 2 characters to search.',
                'reply_markup' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
            ]);
        }

        // Search in both English and Arabic names
        $countries = Country::where('name_en', 'LIKE', "%{$query}%")
            ->orWhere('name_ar', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get();

        if ($countries->isEmpty()) {
            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? "❌ لم يتم العثور على دول بهذا الاسم: {$query}"
                    : "❌ No countries found matching: {$query}",
                'reply_markup' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
            ]);
        }

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $locale === 'ar'
                ? "🔍 نتائج البحث عن: {$query}"
                : "🔍 Search results for: {$query}",
            'reply_markup' => $builder->buildCountrySearchResults($countries, $locale),
        ]);
    }

    private function handleAlertAssetSearch(User $user, string $query, int $chatId, string $locale): mixed
    {
        $query = trim($query);

        if (mb_strlen($query) < 1) {
            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ يرجى إدخال رمز أو اسم الأصل.'
                    : '❌ Please enter an asset symbol or name.',
                'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
            ]);
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

            // Keep waiting for input
            $user->update(['telegram_awaiting_input' => 'alert_asset_search']);

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
            ]);
        }

        // Store search results in draft for selection
        $draft = $user->telegram_alert_draft ?? [];
        $draft['search_results'] = $assets->map(fn ($a) => [
            'id' => $a->id,
            'symbol' => $a->symbol,
            'name' => $locale === 'ar' ? ($a->name_ar ?: $a->name_en) : $a->name_en,
        ])->toArray();
        $user->update([
            'telegram_alert_draft' => $draft,
            'telegram_awaiting_input' => 'alert_asset_select',
        ]);

        // Build asset list text - escape underscores for Markdown
        $assetLines = $assets->map(function ($asset, $index) use ($locale) {
            $name = $locale === 'ar' ? ($asset->name_ar ?: $asset->name_en) : $asset->name_en;
            $symbol = str_replace('_', '\\_', $asset->symbol);
            $name = str_replace('_', '\\_', $name);

            return ($index + 1).". {$symbol} - {$name}";
        })->join("\n");

        // Escape underscores in query for Markdown
        $escapedQuery = str_replace('_', '\\_', $query);

        $text = $locale === 'ar'
            ? "🔍 *نتائج البحث عن: {$escapedQuery}*\n\n{$assetLines}\n\nأرسل رقم الأصل للاختيار:"
            : "🔍 *Search results for: {$escapedQuery}*\n\n{$assetLines}\n\nSend the asset number to select:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
        ]);
    }

    private function handleAlertAssetSelect(User $user, string $input, int $chatId, string $locale): mixed
    {
        $input = trim($input);
        $draft = $user->telegram_alert_draft ?? [];
        $searchResults = $draft['search_results'] ?? [];

        if (empty($searchResults)) {
            // No search results stored, prompt to search again
            $user->update(['telegram_awaiting_input' => 'alert_asset_search']);

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ لم يتم العثور على نتائج. يرجى البحث مرة أخرى:'
                    : '❌ No results found. Please search again:',
                'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
            ]);
        }

        // Try to match input as a number (1-5)
        $selectedIndex = null;
        if (is_numeric($input)) {
            $index = (int) $input - 1;
            if ($index >= 0 && $index < count($searchResults)) {
                $selectedIndex = $index;
            }
        }

        if ($selectedIndex === null) {
            // Invalid selection
            $user->update(['telegram_awaiting_input' => 'alert_asset_select']);

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ اختيار غير صالح. يرجى إدخال رقم من القائمة:'
                    : '❌ Invalid selection. Please enter a number from the list:',
                'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
            ]);
        }

        // Select the asset
        $selectedAsset = $searchResults[$selectedIndex];
        $alertType = $draft['type'] ?? 'price';

        $draft['asset_id'] = $selectedAsset['id'];
        $draft['asset_symbol'] = $selectedAsset['symbol'];
        unset($draft['search_results']);

        Log::info('Asset selected for alert via Telegram', [
            'user_id' => $user->id,
            'asset_id' => $selectedAsset['id'],
            'asset_symbol' => $selectedAsset['symbol'],
        ]);

        // Escape underscores for Markdown
        $symbol = str_replace('_', '\\_', $selectedAsset['symbol']);
        $name = str_replace('_', '\\_', $selectedAsset['name']);

        // Different flow based on alert type
        if ($alertType === 'signal' || $alertType === 'prediction') {
            // Signal/Prediction: skip to direction selection
            $draft['trigger_type'] = $alertType;
            $draft['step'] = 'direction';
            $user->update(['telegram_alert_draft' => $draft]);

            $typeLabel = $alertType === 'signal'
                ? ($locale === 'ar' ? 'تنبيه إشارة' : 'Signal Alert')
                : ($locale === 'ar' ? 'تنبيه توقع' : 'Prediction Alert');

            $text = $locale === 'ar'
                ? "✅ *تم اختيار: {$symbol}*\n{$name}\n\n📊 النوع: {$typeLabel}\n\nمتى يتم تنبيهك؟"
                : "✅ *Selected: {$symbol}*\n{$name}\n\n📊 Type: {$typeLabel}\n\nWhen should we alert you?";

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::alertDirectionKeyboard($locale),
            ]);
        } elseif ($alertType === 'anomaly') {
            // Anomaly: show anomaly type selection
            $draft['step'] = 'anomaly_type';
            $user->update(['telegram_alert_draft' => $draft]);

            $text = $locale === 'ar'
                ? "✅ *تم اختيار: {$symbol}*\n{$name}\n\n⚠️ *تنبيه شذوذ*\n\nاختر نوع الشذوذ:"
                : "✅ *Selected: {$symbol}*\n{$name}\n\n⚠️ *Anomaly Alert*\n\nSelect anomaly type:";

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::anomalyTypeKeyboard($locale),
            ]);
        } elseif ($alertType === 'pattern') {
            // Pattern: show pattern type selection
            $draft['step'] = 'pattern_type';
            $user->update(['telegram_alert_draft' => $draft]);

            $text = $locale === 'ar'
                ? "✅ *تم اختيار: {$symbol}*\n{$name}\n\n📊 *تنبيه نمط*\n\nاختر نوع النمط:"
                : "✅ *Selected: {$symbol}*\n{$name}\n\n📊 *Pattern Alert*\n\nSelect pattern type:";

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::patternTypeKeyboard($locale),
            ]);
        } elseif ($alertType === 'recommendation') {
            // Recommendation: show recommendation type selection
            $draft['step'] = 'recommendation_type';
            $user->update(['telegram_alert_draft' => $draft]);

            $text = $locale === 'ar'
                ? "✅ *تم اختيار: {$symbol}*\n{$name}\n\n💡 *تنبيه توصية*\n\nاختر نوع التوصية:"
                : "✅ *Selected: {$symbol}*\n{$name}\n\n💡 *Recommendation Alert*\n\nSelect recommendation type:";

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::recommendationTypeKeyboard($locale),
            ]);
        } else {
            // Price alert: show trigger type selection
            $draft['step'] = 'trigger';
            $user->update(['telegram_alert_draft' => $draft]);

            $text = $locale === 'ar'
                ? "✅ *تم اختيار: {$symbol}*\n{$name}\n\nاختر نوع التنبيه:"
                : "✅ *Selected: {$symbol}*\n{$name}\n\nSelect alert trigger type:";

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::alertTriggerKeyboard($alertType, $locale),
            ]);
        }
    }

    private function handleAlertTargetPrice(User $user, string $input, int $chatId, string $locale): mixed
    {
        $input = trim($input);

        // Parse the price value
        $price = $this->parseNumericInput($input);

        if ($price === null || $price <= 0) {
            // Keep waiting for input
            $user->update(['telegram_awaiting_input' => 'alert_target_price']);

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ يرجى إدخال سعر صحيح (مثال: 150.50)'
                    : '❌ Please enter a valid price (e.g., 150.50)',
                'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
            ]);
        }

        // Update draft with price value
        $draft = $user->telegram_alert_draft ?? [];
        $draft['step'] = 'direction';
        $draft['parameters'] = $draft['parameters'] ?? [];

        // Use correct parameter name based on trigger type
        $triggerType = $draft['trigger_type'] ?? 'target_price';
        if ($triggerType === 'breakout') {
            $draft['parameters']['level'] = $price;
        } else {
            $draft['parameters']['target_price'] = $price;
        }
        $user->update(['telegram_alert_draft' => $draft]);

        // Show direction selector - escape underscores for Markdown
        $symbol = str_replace('_', '\\_', $draft['asset_symbol'] ?? 'N/A');
        $formattedPrice = number_format($price, 2);

        $text = $locale === 'ar'
            ? "🎯 *تنبيه سعر لـ {$symbol}*\n\nالسعر المستهدف: {$formattedPrice}\n\nمتى يتم تنبيهك؟"
            : "🎯 *Price Alert for {$symbol}*\n\nTarget price: {$formattedPrice}\n\nWhen should we alert you?";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::alertDirectionKeyboard($locale),
        ]);
    }

    private function handleAlertPercentage(User $user, string $input, int $chatId, string $locale): mixed
    {
        $input = trim($input);

        // Parse the percentage value
        $percentage = $this->parseNumericInput($input);

        if ($percentage === null || $percentage <= 0 || $percentage > 100) {
            // Keep waiting for input
            $user->update(['telegram_awaiting_input' => 'alert_percentage']);

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ يرجى إدخال نسبة صحيحة بين 0.1 و 100 (مثال: 5)'
                    : '❌ Please enter a valid percentage between 0.1 and 100 (e.g., 5)',
                'reply_markup' => DefaultKeyboardBuilder::cancelKeyboard($locale),
            ]);
        }

        // Update draft with percentage
        $draft = $user->telegram_alert_draft ?? [];
        $draft['step'] = 'direction';
        $draft['parameters'] = $draft['parameters'] ?? [];
        $draft['parameters']['threshold_percent'] = $percentage;
        $user->update(['telegram_alert_draft' => $draft]);

        // Show direction selector - escape underscores for Markdown
        $symbol = str_replace('_', '\\_', $draft['asset_symbol'] ?? 'N/A');

        $text = $locale === 'ar'
            ? "📊 *تنبيه تغير يومي لـ {$symbol}*\n\nنسبة التغير: {$percentage}%\n\nمتى يتم تنبيهك؟"
            : "📊 *Daily Change Alert for {$symbol}*\n\nChange threshold: {$percentage}%\n\nWhen should we alert you?";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::alertDirectionKeyboard($locale),
        ]);
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
