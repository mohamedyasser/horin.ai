<?php

namespace App\Telegram\Handlers;

use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use App\Models\User;
use App\Telegram\Services\DefaultKeyboardBuilder;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\UpdateHandler;

/**
 * Handler for onboarding text button selections.
 *
 * Handles all onboarding steps via reply keyboard buttons.
 */
class OnboardingTextHandler extends UpdateHandler
{
    private const EXPERIENCE_MAP = [
        '🌱 Beginner' => 'beginner',
        '🌱 مبتدئ' => 'beginner',
        '📈 Intermediate' => 'intermediate',
        '📈 متوسط' => 'intermediate',
        '🎯 Advanced' => 'advanced',
        '🎯 متقدم' => 'advanced',
    ];

    private const RISK_MAP = [
        '🛡️ Conservative' => 'conservative',
        '🛡️ محافظ' => 'conservative',
        '⚖️ Moderate' => 'moderate',
        '⚖️ متوازن' => 'moderate',
        '🚀 Aggressive' => 'aggressive',
        '🚀 مخاطر' => 'aggressive',
    ];

    private const GOAL_MAP = [
        '📈 Growth' => 'capital_growth',
        '📈 نمو رأس المال' => 'capital_growth',
        '💰 Income' => 'fixed_income',
        '💰 دخل ثابت' => 'fixed_income',
        '🛡️ Safety' => 'risk_reduction',
        '🛡️ تقليل المخاطر' => 'risk_reduction',
        '⚡ Speculation' => 'short_term_speculation',
        '⚡ مضاربة' => 'short_term_speculation',
        '🏖️ Retirement' => 'retirement_planning',
        '🏖️ تقاعد' => 'retirement_planning',
        '🏛️ Preserve' => 'wealth_preservation',
        '🏛️ حفظ الثروة' => 'wealth_preservation',
    ];

    private const STYLE_MAP = [
        '📊 Day' => 'day_trading',
        '📊 يومي' => 'day_trading',
        '🔄 Swing' => 'swing_trading',
        '🔄 متأرجح' => 'swing_trading',
        '📅 Position' => 'position_trading',
        '📅 مركز' => 'position_trading',
        '⚡ Scalping' => 'scalping_trading',
        '⚡ سريع' => 'scalping_trading',
    ];

    public function trigger(): bool
    {
        if (! isset($this->update->message?->text)) {
            return false;
        }

        $text = $this->update->message->text;

        // Ignore commands
        if (str_starts_with($text, '/')) {
            return false;
        }

        $telegramId = (string) $this->update->message->from->id;
        $user = User::where('telegram_id', $telegramId)->first();

        Log::debug('OnboardingTextHandler trigger check', [
            'telegram_id' => $telegramId,
            'text' => $text,
            'user_exists' => $user !== null,
            'phone_verified' => $user?->hasVerifiedPhone(),
            'onboarding_complete' => $user?->hasCompletedOnboarding(),
        ]);

        if (! $user || ! $user->hasVerifiedPhone()) {
            return false;
        }

        // Only handle if user hasn't completed onboarding
        if ($user->hasCompletedOnboarding()) {
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

        // Remove checkmark prefix if present
        $text = preg_replace('/^✓\s*/', '', $text);

        $user = User::where('telegram_id', $telegramId)->first();
        $locale = $user->language ?? 'en';
        $builder = new OnboardingKeyboardBuilder;

        // Get current step
        $currentStep = $builder->getCurrentStep($user);

        Log::debug('OnboardingTextHandler handle', [
            'telegram_id' => $telegramId,
            'text' => $text,
            'text_hex' => bin2hex($text),
            'current_step' => $currentStep,
            'experience_level' => $user->experience_level,
        ]);

        // Handle back button
        if ($text === '⬅️ Back' || $text === '⬅️ السابق') {
            return $this->handleBack($chatId, $user, $currentStep, $builder);
        }

        // Handle next button
        if ($text === '➡️ Next' || $text === '➡️ التالي') {
            return $this->handleNext($chatId, $user, $currentStep, $builder);
        }

        // Handle complete button
        if ($text === '✅ Complete' || $text === '✅ إكمال') {
            return $this->handleComplete($chatId, $user, $locale, $builder);
        }

        // Handle step-specific selections
        return match ($currentStep) {
            '1a' => $this->handleExperience($chatId, $user, $text, $builder),
            '1b' => $this->handleRisk($chatId, $user, $text, $builder),
            '2a' => $this->handleGoal($chatId, $user, $text, $builder),
            '2b' => $this->handleStyle($chatId, $user, $text, $builder),
            '3a' => $this->handleCountry($chatId, $user, $text, $builder),
            '3b' => $this->handleMarketToggle($chatId, $user, $text, $builder),
            '4' => $this->handleSectorToggle($chatId, $user, $text, $builder),
            default => null,
        };
    }

    private function handleExperience(int $chatId, User $user, string $text, OnboardingKeyboardBuilder $builder): mixed
    {
        $level = $this->findMatch($text, self::EXPERIENCE_MAP);

        Log::debug('OnboardingTextHandler handleExperience', [
            'text' => $text,
            'level_found' => $level,
            'map_keys' => array_keys(self::EXPERIENCE_MAP),
        ]);

        if (! $level) {
            return null; // Let other handlers process
        }

        $user->update(['experience_level' => $level]);
        $locale = $user->language ?? 'en';

        Log::info('Onboarding: Experience set via Telegram', [
            'user_id' => $user->id,
            'experience_level' => $level,
        ]);

        // Advance to step 1b
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('1b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep1bKeyboard($locale, $user->risk_level),
        ]);

        return null;
    }

    private function handleRisk(int $chatId, User $user, string $text, OnboardingKeyboardBuilder $builder): mixed
    {
        $level = $this->findMatch($text, self::RISK_MAP);

        if (! $level) {
            return null;
        }

        $user->update(['risk_level' => $level]);
        $locale = $user->language ?? 'en';

        Log::info('Onboarding: Risk set via Telegram', [
            'user_id' => $user->id,
            'risk_level' => $level,
        ]);

        // Advance to step 2a
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('2a', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep2aKeyboard($locale, $user->investment_goal),
        ]);

        return null;
    }

    private function handleGoal(int $chatId, User $user, string $text, OnboardingKeyboardBuilder $builder): mixed
    {
        $goal = $this->findMatch($text, self::GOAL_MAP);

        if (! $goal) {
            return null;
        }

        $user->update(['investment_goal' => $goal]);
        $locale = $user->language ?? 'en';

        Log::info('Onboarding: Goal set via Telegram', [
            'user_id' => $user->id,
            'investment_goal' => $goal,
        ]);

        // Advance to step 2b
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('2b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep2bKeyboard($locale, $user->trading_style),
        ]);

        return null;
    }

    private function handleStyle(int $chatId, User $user, string $text, OnboardingKeyboardBuilder $builder): mixed
    {
        $style = $this->findMatch($text, self::STYLE_MAP);

        if (! $style) {
            return null;
        }

        $user->update(['trading_style' => $style]);
        $locale = $user->language ?? 'en';

        Log::info('Onboarding: Style set via Telegram', [
            'user_id' => $user->id,
            'trading_style' => $style,
        ]);

        // Advance to step 3a
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('3a', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
        ]);

        return null;
    }

    private function handleCountry(int $chatId, User $user, string $text, OnboardingKeyboardBuilder $builder): mixed
    {
        $locale = $user->language ?? 'en';

        // Try to find country by name in button text
        $country = $this->findCountryByText($text, $locale);

        if (! $country) {
            return null;
        }

        $user->update(['country_id' => $country->id]);

        Log::info('Onboarding: Country set via Telegram', [
            'user_id' => $user->id,
            'country_id' => $country->id,
        ]);

        // Advance to step 3b (markets)
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('3b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep3MarketsKeyboard($locale, $country->id, $user->markets()->pluck('markets.id')->toArray()),
        ]);

        return null;
    }

    private function handleMarketToggle(int $chatId, User $user, string $text, OnboardingKeyboardBuilder $builder): mixed
    {
        $locale = $user->language ?? 'en';

        if (! $user->country_id) {
            return null;
        }

        // Find market by name in button text
        $market = $this->findMarketByText($text, $user->country_id, $locale);

        if (! $market) {
            return null;
        }

        // Toggle market selection
        $currentMarkets = $user->markets()->pluck('markets.id')->toArray();

        if (in_array($market->id, $currentMarkets, true)) {
            $user->markets()->detach($market->id);
            $currentMarkets = array_filter($currentMarkets, fn ($id) => $id !== $market->id);
        } else {
            $user->markets()->attach($market->id);
            $currentMarkets[] = $market->id;
        }

        Log::info('Onboarding: Market toggled via Telegram', [
            'user_id' => $user->id,
            'market_id' => $market->id,
        ]);

        // Refresh the keyboard
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('3b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep3MarketsKeyboard($locale, $user->country_id, $currentMarkets),
        ]);

        return null;
    }

    private function handleSectorToggle(int $chatId, User $user, string $text, OnboardingKeyboardBuilder $builder): mixed
    {
        $locale = $user->language ?? 'en';

        // Find sector by name in button text
        $sector = $this->findSectorByText($text, $locale);

        if (! $sector) {
            return null;
        }

        // Toggle sector selection
        $currentSectors = $user->sectors()->pluck('sectors.id')->toArray();

        if (in_array($sector->id, $currentSectors, true)) {
            $user->sectors()->detach($sector->id);
            $currentSectors = array_filter($currentSectors, fn ($id) => $id !== $sector->id);
        } else {
            $user->sectors()->attach($sector->id);
            $currentSectors[] = $sector->id;
        }

        Log::info('Onboarding: Sector toggled via Telegram', [
            'user_id' => $user->id,
            'sector_id' => $sector->id,
        ]);

        // Refresh the keyboard
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('4', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep4Keyboard($locale, $currentSectors),
        ]);

        return null;
    }

    private function handleBack(int $chatId, User $user, string $currentStep, OnboardingKeyboardBuilder $builder): mixed
    {
        $locale = $user->language ?? 'en';

        $previousStep = match ($currentStep) {
            '1b' => '1a',
            '2a' => '1b',
            '2b' => '2a',
            '3a' => '2b',
            '3b' => '3a',
            '4' => '3b',
            default => null,
        };

        if (! $previousStep) {
            return null;
        }

        $keyboard = match ($previousStep) {
            '1a' => $builder->buildStep1aKeyboard($locale, $user->experience_level),
            '1b' => $builder->buildStep1bKeyboard($locale, $user->risk_level),
            '2a' => $builder->buildStep2aKeyboard($locale, $user->investment_goal),
            '2b' => $builder->buildStep2bKeyboard($locale, $user->trading_style),
            '3a' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
            '3b' => $builder->buildStep3MarketsKeyboard($locale, $user->country_id, $user->markets()->pluck('markets.id')->toArray()),
            default => [],
        };

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage($previousStep, $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);

        return null;
    }

    private function handleNext(int $chatId, User $user, string $currentStep, OnboardingKeyboardBuilder $builder): mixed
    {
        $locale = $user->language ?? 'en';

        // Only step 3b has a next button (to go to step 4)
        if ($currentStep !== '3b') {
            return null;
        }

        // Check if user has selected at least one market
        if ($user->markets()->count() === 0) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar' ? 'يرجى اختيار سوق واحد على الأقل.' : 'Please select at least one market.',
            ]);

            return null;
        }

        // Advance to step 4
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('4', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep4Keyboard($locale, $user->sectors()->pluck('sectors.id')->toArray()),
        ]);

        return null;
    }

    private function handleComplete(int $chatId, User $user, string $locale, OnboardingKeyboardBuilder $builder): mixed
    {
        // Check if user has selected at least one sector
        if ($user->sectors()->count() === 0) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar' ? 'يرجى اختيار قطاع واحد على الأقل.' : 'Please select at least one sector.',
            ]);

            return null;
        }

        $user->markOnboardingAsComplete();

        Log::info('Onboarding: Completed via Telegram', [
            'user_id' => $user->id,
        ]);

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getCompletionMessage($locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::forUser($user, $locale),
        ]);

        return null;
    }

    private function findMatch(string $text, array $map): ?string
    {
        foreach ($map as $pattern => $value) {
            if (str_contains($text, $pattern) || $text === $pattern) {
                return $value;
            }
        }

        return null;
    }

    private function findCountryByText(string $text, string $locale): ?Country
    {
        // Remove flag emoji and checkmark
        $text = preg_replace('/^[✓✅⬜]\s*/', '', $text);
        $text = preg_replace('/^[\x{1F1E0}-\x{1F1FF}]{2}\s*/u', '', $text);
        $text = trim($text);

        if ($locale === 'ar') {
            return Country::where('name_ar', $text)->first();
        }

        return Country::where('name_en', $text)->first();
    }

    private function findMarketByText(string $text, string $countryId, string $locale): ?Market
    {
        // Remove checkbox emoji
        $text = preg_replace('/^[✅⬜]\s*/', '', $text);
        $text = trim($text);

        $query = Market::where('country_id', $countryId);

        if ($locale === 'ar') {
            return $query->where(function ($q) use ($text) {
                $q->where('name_ar', $text)->orWhere('name_en', $text);
            })->first();
        }

        return $query->where('name_en', $text)->first();
    }

    private function findSectorByText(string $text, string $locale): ?Sector
    {
        // Remove checkbox emoji
        $text = preg_replace('/^[✅⬜]\s*/', '', $text);
        $text = trim($text);

        if ($locale === 'ar') {
            return Sector::where(function ($q) use ($text) {
                $q->where('name_ar', $text)->orWhere('name_en', $text);
            })->first();
        }

        return Sector::where('name_en', $text)->first();
    }
}
