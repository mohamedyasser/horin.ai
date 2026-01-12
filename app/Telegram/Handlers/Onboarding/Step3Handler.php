<?php

namespace App\Telegram\Handlers\Onboarding;

use App\Models\Country;
use App\Models\Market;
use App\Models\User;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Step 3: Country (3a) & Markets (3b) selection.
 *
 * Callback patterns:
 * - ob:country:{id} - Select country, then auto-advance to markets
 * - ob:country:search - Trigger country search mode
 * - ob:country:change - Go back to country selection
 * - ob:market:toggle:{id} - Toggle market selection
 * - ob:market:page:{n} - Change markets pagination page
 * - ob:step3a:back - Go back from country to style (step 2b)
 * - ob:step3b:back - Go back from markets to country (step 3a)
 * - ob:step3:next - Proceed to step 4
 */
class Step3Handler extends CallbackHandler
{
    protected string $match = '/^ob:(country|market|step3|step3a|step3b):/';

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
        $action = $parts[1];
        $value = $parts[2] ?? null;
        $extra = $parts[3] ?? null;

        $locale = $user->language ?? 'en';
        $builder = new OnboardingKeyboardBuilder;

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        return match ($action) {
            'country' => $this->handleCountry($user, $value, $locale, $builder, $chatId, $messageId),
            'market' => $this->handleMarket($user, $value, $extra, $locale, $builder, $chatId, $messageId),
            'step3a' => $this->handleBackToStyle($user, $locale, $builder, $chatId, $messageId),
            'step3b' => $this->handleBackToCountry($user, $locale, $builder, $chatId, $messageId),
            'step3' => $this->handleNext($user, $value, $locale, $builder, $chatId, $messageId),
            default => $this->answerWithError('Invalid action'),
        };
    }

    private function handleCountry(User $user, ?string $value, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        if ($value === 'search') {
            // Set user state to awaiting country search input
            $user->update(['telegram_awaiting_input' => 'country_search']);

            $text = $locale === 'ar'
                ? '🔍 أدخل اسم الدولة للبحث:'
                : '🔍 Type the country name to search:';

            // Send a new message asking for input (keep original message)
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => [
                    'force_reply' => true,
                    'selective' => true,
                ],
            ]);

            $this->answerCallbackQuery(['text' => '']);

            return null;
        }

        if ($value === 'change') {
            // Clear country and markets, show country selection again
            $user->markets()->detach();
            $user->update(['country_id' => null]);

            $this->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $builder->getStepMessage('3a', $locale),
                'parse_mode' => 'Markdown',
                'reply_markup' => [
                    'inline_keyboard' => $builder->buildStep3CountryKeyboard($locale),
                ],
            ]);

            $this->answerCallbackQuery(['text' => '']);

            return null;
        }

        // Handle country selection by ID
        $country = Country::find($value);
        if (! $country) {
            return $this->answerWithError('Country not found');
        }

        // If country changed, clear previous market selections
        if ($user->country_id !== $country->id) {
            $user->markets()->detach();
        }

        $user->update(['country_id' => $country->id]);

        Log::info('Onboarding: Country selected via Telegram', [
            'user_id' => $user->id,
            'country_id' => $country->id,
        ]);

        $countryName = $locale === 'ar' ? $country->name_ar : $country->name_en;

        // Auto-advance to step 3b (markets)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('3b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep3MarketsKeyboard($locale, $country->id),
            ],
        ]);

        $this->answerCallbackQuery([
            'text' => "✓ {$countryName}",
            'show_alert' => false,
        ]);

        return null;
    }

    private function handleMarket(User $user, ?string $value, ?string $extra, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        // Handle pagination
        if ($value === 'page' && $extra !== null) {
            $page = (int) $extra;

            return $this->updateMarketsKeyboard($user, $locale, $builder, $chatId, $messageId, $page);
        }

        // Handle market toggle
        if ($value === 'toggle' && $extra !== null) {
            $marketId = $extra;
            $market = Market::find($marketId);

            if (! $market) {
                return $this->answerWithError('Market not found');
            }

            // Toggle market selection
            $isSelected = $user->markets()->where('markets.id', $marketId)->exists();

            if ($isSelected) {
                $user->markets()->detach($marketId);
                $action = 'removed';
            } else {
                $user->markets()->attach($marketId);
                $action = 'added';
            }

            Log::info("Onboarding: Market {$action} via Telegram", [
                'user_id' => $user->id,
                'market_id' => $marketId,
            ]);

            // Update keyboard
            $this->updateMarketsKeyboard($user, $locale, $builder, $chatId, $messageId);

            $marketName = $locale === 'ar' ? ($market->name_ar ?: $market->name_en) : $market->name_en;
            $this->answerCallbackQuery([
                'text' => $action === 'added'
                    ? ($locale === 'ar' ? "✅ {$marketName}" : "✅ {$marketName}")
                    : ($locale === 'ar' ? "❌ {$marketName}" : "❌ {$marketName}"),
                'show_alert' => false,
            ]);

            return null;
        }

        return $this->answerWithError('Invalid market action');
    }

    private function handleBackToStyle(User $user, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        // Go back to step 2b (style)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('2b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep2bKeyboard($locale, $user->trading_style),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleBackToCountry(User $user, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        // Go back to step 3a (country)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('3a', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleNext(User $user, ?string $value, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        if ($value !== 'next') {
            return $this->answerWithError('Invalid navigation');
        }

        // Validate step 3 is complete
        if (! $user->country_id || $user->markets()->count() === 0) {
            return $this->answerWithError(
                $locale === 'ar'
                    ? 'يرجى اختيار سوق واحد على الأقل'
                    : 'Please select at least one market'
            );
        }

        // Move to step 4 (sectors)
        $selectedSectors = $user->sectors()->pluck('sectors.id')->toArray();

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('4', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep4Keyboard($locale, $selectedSectors),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function updateMarketsKeyboard(User $user, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId, int $page = 1): mixed
    {
        // Refresh user data
        $user->refresh();

        $selectedMarketIds = $user->markets()->pluck('markets.id')->toArray();

        $this->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep3MarketsKeyboard($locale, $user->country_id, $selectedMarketIds, $page),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
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
