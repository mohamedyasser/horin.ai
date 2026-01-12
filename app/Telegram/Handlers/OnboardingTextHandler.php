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

        // If step is 'complete', mark onboarding and show main menu
        if ($currentStep === 'complete') {
            if (! $user->hasCompletedOnboarding()) {
                $user->markOnboardingAsComplete();
            }

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $builder->getCompletionMessage($locale),
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::forUser($user, $locale),
            ]);

            return true;
        }

        // Handle step-specific selections
        $result = match ($currentStep) {
            '1a' => $this->handleExperience($chatId, $user, $text, $builder),
            '1b' => $this->handleRisk($chatId, $user, $text, $builder),
            '2a' => $this->handleGoal($chatId, $user, $text, $builder),
            '2b' => $this->handleStyle($chatId, $user, $text, $builder),
            '3a' => $this->handleCountry($chatId, $user, $text, $builder),
            '3b' => $this->handleMarketToggle($chatId, $user, $text, $builder),
            '4' => $this->handleSectorToggle($chatId, $user, $text, $builder),
            default => null,
        };

        // If no handler matched, show current step's keyboard again
        if ($result === null) {
            return $this->showCurrentStepAgain($chatId, $user, $currentStep, $builder, $locale);
        }

        return $result;
    }

    /**
     * Re-show the current step keyboard when input wasn't recognized.
     */
    private function showCurrentStepAgain(int $chatId, User $user, string $currentStep, OnboardingKeyboardBuilder $builder, string $locale): mixed
    {
        // If somehow we reach here with 'complete', show main menu
        if ($currentStep === 'complete') {
            if (! $user->hasCompletedOnboarding()) {
                $user->markOnboardingAsComplete();
            }

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $builder->getCompletionMessage($locale),
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::forUser($user, $locale),
            ]);

            return true;
        }

        $errorText = $locale === 'ar'
            ? 'يرجى اختيار أحد الخيارات من القائمة أدناه:'
            : 'Please choose from the options below:';

        $keyboard = match ($currentStep) {
            '1a' => $builder->buildStep1aKeyboard($locale, $user->experience_level),
            '1b' => $builder->buildStep1bKeyboard($locale, $user->risk_level),
            '2a' => $builder->buildStep2aKeyboard($locale, $user->investment_goal),
            '2b' => $builder->buildStep2bKeyboard($locale, $user->trading_style),
            '3a' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
            '3b' => $builder->buildStep3MarketsKeyboard($locale, $user->country_id, $user->markets()->pluck('markets.id')->toArray()),
            '4' => $builder->buildStep4Keyboard($locale, $user->sectors()->pluck('sectors.id')->toArray()),
            default => DefaultKeyboardBuilder::forUser($user, $locale),
        };

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $errorText,
            'reply_markup' => $keyboard,
        ]);

        return null;
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

        return true;
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

        return true;
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

        return true;
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

        return true;
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

        return true;
    }

    private function handleMarketToggle(int $chatId, User $user, string $text, OnboardingKeyboardBuilder $builder): mixed
    {
        $locale = $user->language ?? 'en';

        if (! $user->country_id) {
            Log::debug('handleMarketToggle: No country_id', ['user_id' => $user->id]);

            return null;
        }

        // Find market by name in button text
        $market = $this->findMarketByText($text, $user->country_id, $locale);

        Log::debug('handleMarketToggle: Finding market', [
            'text' => $text,
            'text_hex' => bin2hex($text),
            'country_id' => $user->country_id,
            'locale' => $locale,
            'market_found' => $market?->id,
        ]);

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

        // Count total markets available for this country
        $totalMarketsCount = Market::where('country_id', $user->country_id)->count();

        // If only one market exists and user just selected it, auto-advance to sectors
        if ($totalMarketsCount === 1 && count($currentMarkets) === 1) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $builder->getStepMessage('4', $locale),
                'parse_mode' => 'Markdown',
                'reply_markup' => $builder->buildStep4Keyboard($locale, $user->sectors()->pluck('sectors.id')->toArray()),
            ]);

            return true;
        }

        // Refresh the keyboard
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('3b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep3MarketsKeyboard($locale, $user->country_id, $currentMarkets),
        ]);

        return true;
    }

    private function handleSectorToggle(int $chatId, User $user, string $text, OnboardingKeyboardBuilder $builder): mixed
    {
        $locale = $user->language ?? 'en';

        Log::debug('handleSectorToggle: Attempting to find sector', [
            'text' => $text,
            'text_hex' => bin2hex($text),
            'locale' => $locale,
        ]);

        // Find sector by name in button text
        $sector = $this->findSectorByText($text, $locale);

        Log::debug('handleSectorToggle: Sector search result', [
            'sector_found' => $sector?->id,
            'sector_name' => $sector?->name_en,
        ]);

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

        // Count total sectors available
        $totalSectorsCount = Sector::count();

        // If only one sector exists and user just selected it, auto-complete onboarding
        if ($totalSectorsCount === 1 && count($currentSectors) === 1) {
            $user->markOnboardingAsComplete();

            Log::info('Onboarding: Completed via Telegram (auto)', [
                'user_id' => $user->id,
            ]);

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $builder->getCompletionMessage($locale),
                'parse_mode' => 'Markdown',
                'reply_markup' => DefaultKeyboardBuilder::forUser($user, $locale),
            ]);

            return true;
        }

        // Refresh the keyboard
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('4', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep4Keyboard($locale, $currentSectors),
        ]);

        return true;
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

        return true;
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

            return true;
        }

        // Advance to step 4
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getStepMessage('4', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => $builder->buildStep4Keyboard($locale, $user->sectors()->pluck('sectors.id')->toArray()),
        ]);

        return true;
    }

    private function handleComplete(int $chatId, User $user, string $locale, OnboardingKeyboardBuilder $builder): mixed
    {
        // Check if user has selected at least one sector
        if ($user->sectors()->count() === 0) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar' ? 'يرجى اختيار قطاع واحد على الأقل.' : 'Please select at least one sector.',
            ]);

            return true;
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

        return true;
    }

    private function findMatch(string $text, array $map): ?string
    {
        // Normalize whitespace (replace various Unicode spaces with regular space)
        $normalizedText = preg_replace('/[\x{00A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $text);
        $normalizedText = preg_replace('/\s+/', ' ', $normalizedText);
        $normalizedText = trim($normalizedText);

        foreach ($map as $pattern => $value) {
            // Normalize pattern too
            $normalizedPattern = preg_replace('/[\x{00A0}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $pattern);
            $normalizedPattern = preg_replace('/\s+/', ' ', $normalizedPattern);
            $normalizedPattern = trim($normalizedPattern);

            if ($normalizedText === $normalizedPattern || str_contains($normalizedText, $normalizedPattern)) {
                return $value;
            }
        }

        return null;
    }

    private function findCountryByText(string $text, string $locale): ?Country
    {
        $originalText = $text;

        // Remove flag emoji and checkmark (use 'u' flag for Unicode)
        $text = preg_replace('/^[\x{2713}\x{2705}\x{2B1C}]\s*/u', '', $text) ?? $text;
        $text = preg_replace('/^[\x{1F1E0}-\x{1F1FF}]{2}\s*/u', '', $text) ?? $text;
        $text = trim($text);

        Log::debug('findCountryByText: After cleanup', [
            'original' => $originalText,
            'cleaned' => $text,
            'locale' => $locale,
        ]);

        // Try flexible match
        $countries = Country::all();

        foreach ($countries as $country) {
            $nameEn = trim($country->name_en ?? '');
            $nameAr = trim($country->name_ar ?? '');

            if ($text === $nameEn || $text === $nameAr) {
                return $country;
            }

            // Also try case-insensitive match
            if (mb_strtolower($text) === mb_strtolower($nameEn) || mb_strtolower($text) === mb_strtolower($nameAr)) {
                return $country;
            }
        }

        return null;
    }

    private function findMarketByText(string $text, string $countryId, string $locale): ?Market
    {
        $originalText = $text;

        // Remove checkbox emoji (use 'u' flag for Unicode)
        $text = preg_replace('/^[\x{2705}\x{2B1C}]\s*/u', '', $text) ?? $text;
        $text = trim($text);

        Log::debug('findMarketByText: After cleanup', [
            'original' => $originalText,
            'cleaned' => $text,
            'country_id' => $countryId,
            'locale' => $locale,
        ]);

        $query = Market::where('country_id', $countryId);

        // Get all markets for debugging
        $allMarkets = $query->get(['id', 'name_en', 'name_ar']);
        Log::debug('findMarketByText: Available markets', [
            'markets' => $allMarkets->map(fn ($m) => [
                'id' => $m->id,
                'name_en' => $m->name_en,
                'name_ar' => $m->name_ar,
            ])->toArray(),
        ]);

        // Try exact match first, then try flexible match
        $markets = Market::where('country_id', $countryId)->get();

        foreach ($markets as $market) {
            $nameEn = trim($market->name_en ?? '');
            $nameAr = trim($market->name_ar ?? '');

            if ($text === $nameEn || $text === $nameAr) {
                return $market;
            }

            // Also try case-insensitive match
            if (mb_strtolower($text) === mb_strtolower($nameEn) || mb_strtolower($text) === mb_strtolower($nameAr)) {
                return $market;
            }
        }

        return null;
    }

    private function findSectorByText(string $text, string $locale): ?Sector
    {
        $originalText = $text;

        // Remove checkbox/check emoji variants (use 'u' flag for Unicode)
        // ✅ U+2705, ⬜ U+2B1C, ☐ U+2610, ☑ U+2611, □ U+25A1, ✓ U+2713
        $text = preg_replace('/^[\x{2705}\x{2B1C}\x{2610}\x{2611}\x{25A1}\x{2713}]\s*/u', '', $text) ?? $text;
        $text = trim($text);

        Log::debug('findSectorByText: After cleanup', [
            'original' => $originalText,
            'original_hex' => bin2hex($originalText),
            'cleaned' => $text,
            'locale' => $locale,
        ]);

        // Try exact match first, then try flexible match
        $sectors = Sector::all();

        Log::debug('findSectorByText: Available sectors', [
            'sectors' => $sectors->map(fn ($s) => [
                'id' => $s->id,
                'name_en' => $s->name_en,
                'name_ar' => $s->name_ar,
            ])->toArray(),
        ]);

        foreach ($sectors as $sector) {
            $nameEn = trim($sector->name_en ?? '');
            $nameAr = trim($sector->name_ar ?? '');

            if ($text === $nameEn || $text === $nameAr) {
                Log::debug('findSectorByText: Exact match found', ['sector_id' => $sector->id]);

                return $sector;
            }

            // Also try case-insensitive match
            if (mb_strtolower($text) === mb_strtolower($nameEn) || mb_strtolower($text) === mb_strtolower($nameAr)) {
                Log::debug('findSectorByText: Case-insensitive match found', ['sector_id' => $sector->id]);

                return $sector;
            }

            // Try contains match as last resort
            if (mb_stripos($nameEn, $text) !== false || mb_stripos($nameAr, $text) !== false ||
                mb_stripos($text, $nameEn) !== false || mb_stripos($text, $nameAr) !== false) {
                Log::debug('findSectorByText: Contains match found', ['sector_id' => $sector->id]);

                return $sector;
            }
        }

        Log::debug('findSectorByText: No match found');

        return null;
    }
}
