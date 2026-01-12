<?php

namespace App\Telegram\Services;

use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use App\Models\User;

class OnboardingKeyboardBuilder
{
    /**
     * Experience level options with icons.
     */
    private const EXPERIENCE_LEVELS = [
        'beginner' => ['en' => 'Beginner', 'ar' => 'مبتدئ', 'icon' => '🌱'],
        'intermediate' => ['en' => 'Intermediate', 'ar' => 'متوسط', 'icon' => '📈'],
        'advanced' => ['en' => 'Advanced', 'ar' => 'متقدم', 'icon' => '🎯'],
    ];

    /**
     * Risk level options with icons.
     */
    private const RISK_LEVELS = [
        'conservative' => ['en' => 'Conservative', 'ar' => 'محافظ', 'icon' => '🛡️'],
        'moderate' => ['en' => 'Moderate', 'ar' => 'متوازن', 'icon' => '⚖️'],
        'aggressive' => ['en' => 'Aggressive', 'ar' => 'مخاطر', 'icon' => '🚀'],
    ];

    /**
     * Investment goal options with icons.
     */
    private const INVESTMENT_GOALS = [
        'capital_growth' => ['en' => 'Growth', 'ar' => 'نمو رأس المال', 'icon' => '📈'],
        'fixed_income' => ['en' => 'Income', 'ar' => 'دخل ثابت', 'icon' => '💰'],
        'risk_reduction' => ['en' => 'Safety', 'ar' => 'تقليل المخاطر', 'icon' => '🛡️'],
        'short_term_speculation' => ['en' => 'Speculation', 'ar' => 'مضاربة', 'icon' => '⚡'],
        'retirement_planning' => ['en' => 'Retirement', 'ar' => 'تقاعد', 'icon' => '🏖️'],
        'wealth_preservation' => ['en' => 'Preserve', 'ar' => 'حفظ الثروة', 'icon' => '🏛️'],
        'passive_income' => ['en' => 'Passive', 'ar' => 'دخل سلبي', 'icon' => '💸'],
        'education_savings' => ['en' => 'Education', 'ar' => 'تعليم', 'icon' => '🎓'],
        'home_purchase' => ['en' => 'Home', 'ar' => 'شراء منزل', 'icon' => '🏠'],
        'emergency_fund' => ['en' => 'Emergency', 'ar' => 'طوارئ', 'icon' => '🆘'],
    ];

    /**
     * Trading style options with icons.
     */
    private const TRADING_STYLES = [
        'day_trading' => ['en' => 'Day', 'ar' => 'يومي', 'icon' => '📊'],
        'swing_trading' => ['en' => 'Swing', 'ar' => 'متأرجح', 'icon' => '🔄'],
        'position_trading' => ['en' => 'Position', 'ar' => 'مركز', 'icon' => '📅'],
        'scalping_trading' => ['en' => 'Scalping', 'ar' => 'سريع', 'icon' => '⚡'],
    ];

    /**
     * Popular countries by code for quick selection.
     */
    private const POPULAR_COUNTRY_CODES = ['EG', 'SA'];

    /**
     * Build Step 1a keyboard: Experience selection only.
     */
    public function buildStep1aKeyboard(string $locale, ?string $selectedExp = null): array
    {
        $keyboard = [];

        // Experience level buttons in single column for clarity
        foreach (self::EXPERIENCE_LEVELS as $key => $labels) {
            $isSelected = $selectedExp === $key;
            $text = $isSelected
                ? "✓ {$labels['icon']} {$labels[$locale]}"
                : "{$labels['icon']} {$labels[$locale]}";
            $keyboard[] = [['text' => $text]];
        }

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Build Step 1b keyboard: Risk selection only.
     */
    public function buildStep1bKeyboard(string $locale, ?string $selectedRisk = null): array
    {
        $keyboard = [];

        // Risk level buttons in single column for clarity
        foreach (self::RISK_LEVELS as $key => $labels) {
            $isSelected = $selectedRisk === $key;
            $text = $isSelected
                ? "✓ {$labels['icon']} {$labels[$locale]}"
                : "{$labels['icon']} {$labels[$locale]}";
            $keyboard[] = [['text' => $text]];
        }

        // Back button
        $keyboard[] = [['text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back']];

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Build Step 2a keyboard: Investment Goal selection only.
     * Shows all goals without pagination for simplicity with reply keyboard.
     */
    public function buildStep2aKeyboard(string $locale, ?string $selectedGoal = null, int $goalPage = 1): array
    {
        $keyboard = [];

        // Investment goals - show first 6 most common
        $commonGoals = ['capital_growth', 'fixed_income', 'risk_reduction', 'short_term_speculation', 'retirement_planning', 'wealth_preservation'];

        // Goals in rows of 2
        $chunks = array_chunk($commonGoals, 2);
        foreach ($chunks as $chunk) {
            $row = [];
            foreach ($chunk as $key) {
                $labels = self::INVESTMENT_GOALS[$key];
                $isSelected = $selectedGoal === $key;
                $text = $isSelected
                    ? "✓ {$labels['icon']} {$labels[$locale]}"
                    : "{$labels['icon']} {$labels[$locale]}";
                $row[] = ['text' => $text];
            }
            $keyboard[] = $row;
        }

        // Back button
        $keyboard[] = [['text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back']];

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Build Step 2b keyboard: Trading Style selection only.
     */
    public function buildStep2bKeyboard(string $locale, ?string $selectedStyle = null): array
    {
        $keyboard = [];

        // Trading style buttons in rows of 2
        $styles = array_keys(self::TRADING_STYLES);
        $chunks = array_chunk($styles, 2);
        foreach ($chunks as $chunk) {
            $row = [];
            foreach ($chunk as $key) {
                $labels = self::TRADING_STYLES[$key];
                $isSelected = $selectedStyle === $key;
                $text = $isSelected
                    ? "✓ {$labels['icon']} {$labels[$locale]}"
                    : "{$labels['icon']} {$labels[$locale]}";
                $row[] = ['text' => $text];
            }
            $keyboard[] = $row;
        }

        // Back button
        $keyboard[] = [['text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back']];

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Build Step 3 keyboard: Country & Markets selection.
     *
     * @param  array{country_id?: string|null, markets?: array<string>}  $selected
     */
    public function buildStep3CountryKeyboard(string $locale, ?string $selectedCountryId = null): array
    {
        $keyboard = [];

        // Popular countries - use CASE WHEN for PostgreSQL-compatible ordering
        $orderCase = 'CASE ';
        foreach (self::POPULAR_COUNTRY_CODES as $index => $code) {
            $orderCase .= "WHEN code = '{$code}' THEN {$index} ";
        }
        $orderCase .= 'END';

        $popularCountries = Country::whereIn('code', self::POPULAR_COUNTRY_CODES)
            ->orderByRaw($orderCase)
            ->get();

        // Country flags by code
        $flags = [
            'EG' => '🇪🇬',
            'SA' => '🇸🇦',
        ];

        // Countries in rows of 2
        $countryRows = $popularCountries->chunk(2);
        foreach ($countryRows as $row) {
            $btns = [];
            foreach ($row as $country) {
                $name = $locale === 'ar' ? $country->name_ar : $country->name_en;
                $flag = $flags[$country->code] ?? '🏳️';
                $isSelected = $selectedCountryId === $country->id;
                $text = $isSelected ? "✓ {$flag} {$name}" : "{$flag} {$name}";
                $btns[] = ['text' => $text];
            }
            $keyboard[] = $btns;
        }

        // Back button - goes to step 2b (style)
        $keyboard[] = [['text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back']];

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Build Step 3 markets keyboard after country is selected.
     *
     * @param  array<string>  $selectedMarketIds
     */
    public function buildStep3MarketsKeyboard(string $locale, string $countryId, array $selectedMarketIds = [], int $page = 1): array
    {
        $keyboard = [];

        // Get markets for the selected country
        $markets = Market::where('country_id', $countryId)->get();

        if ($markets->isEmpty()) {
            // No markets - show back only
            $keyboard[] = [['text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back']];

            return [
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        // Markets in rows of 2
        $marketRows = $markets->chunk(2);
        foreach ($marketRows as $row) {
            $btns = [];
            foreach ($row as $market) {
                $name = $locale === 'ar' ? ($market->name_ar ?: $market->name_en) : $market->name_en;
                $isSelected = in_array($market->id, $selectedMarketIds, true);
                $text = $isSelected ? "✅ {$name}" : "⬜ {$name}";
                $btns[] = ['text' => $text];
            }
            $keyboard[] = $btns;
        }

        // Selected count
        $selectedCount = count($selectedMarketIds);

        // Back / Next row
        $navRow = [];
        $navRow[] = ['text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back'];
        if ($selectedCount > 0) {
            $navRow[] = ['text' => $locale === 'ar' ? '➡️ التالي' : '➡️ Next'];
        }
        $keyboard[] = $navRow;

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Build Step 4 keyboard: Sectors selection.
     *
     * @param  array<string>  $selectedSectorIds
     */
    public function buildStep4Keyboard(string $locale, array $selectedSectorIds = [], int $page = 1): array
    {
        $keyboard = [];

        // Get all sectors
        $sectors = Sector::all();

        if ($sectors->isEmpty()) {
            $keyboard[] = [['text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back']];

            return [
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        // Sectors in rows of 2
        $sectorRows = $sectors->chunk(2);
        foreach ($sectorRows as $row) {
            $btns = [];
            foreach ($row as $sector) {
                $name = $locale === 'ar' ? ($sector->name_ar ?: $sector->name_en) : $sector->name_en;
                $isSelected = in_array($sector->id, $selectedSectorIds, true);
                $text = $isSelected ? "✅ {$name}" : "⬜ {$name}";
                $btns[] = ['text' => $text];
            }
            $keyboard[] = $btns;
        }

        // Selected count
        $selectedCount = count($selectedSectorIds);

        // Back / Complete
        $navRow = [];
        $navRow[] = ['text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back'];
        if ($selectedCount > 0) {
            $navRow[] = ['text' => $locale === 'ar' ? '✅ إكمال' : '✅ Complete'];
        }
        $keyboard[] = $navRow;

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Build the step message text.
     */
    public function getStepMessage(string $step, string $locale): string
    {
        return match ($step) {
            '1a' => $locale === 'ar'
                ? 'ما هو مستوى خبرتك في التداول؟'
                : "What's your trading experience?",
            '1b' => $locale === 'ar'
                ? 'ما هو مستوى تحملك للمخاطر؟'
                : "What's your risk tolerance?",
            '2a' => $locale === 'ar'
                ? 'ما هو هدفك الاستثماري؟'
                : "What's your investment goal?",
            '2b' => $locale === 'ar'
                ? 'ما هو أسلوب التداول المفضل لديك؟'
                : "What's your trading style?",
            '3a' => $locale === 'ar'
                ? 'اختر دولتك:'
                : 'Select your country:',
            '3b' => $locale === 'ar'
                ? 'اختر الأسواق:'
                : 'Select markets:',
            '4' => $locale === 'ar'
                ? 'اختر القطاعات:'
                : 'Select sectors:',
            default => '',
        };
    }

    /**
     * Build completion message.
     */
    public function getCompletionMessage(string $locale): string
    {
        if ($locale === 'ar') {
            return <<<'MSG'
🎉 *تم إكمال الإعداد بنجاح!*

أنت الآن جاهز لتلقي تنبيهات الأسهم المخصصة.

*الأوامر المتاحة:*
📋 /alerts - عرض تنبيهاتك
⚙️ /settings - إعدادات الإشعارات
🌐 /language - تغيير اللغة
❓ /help - المساعدة
MSG;
        }

        return <<<'MSG'
🎉 *Setup Complete!*

You're all set to receive personalized stock alerts.

*Available Commands:*
📋 /alerts - View your alerts
⚙️ /settings - Notification settings
🌐 /language - Change language
❓ /help - Get help
MSG;
    }

    /**
     * Build completion keyboard - main menu keyboard.
     */
    public function buildCompletionKeyboard(string $locale): array
    {
        return \App\Telegram\Services\DefaultKeyboardBuilder::mainMenuKeyboard($locale);
    }

    /**
     * Get the current onboarding step for a user based on their data.
     * Returns string like '1a', '1b', '2a', '2b', '3a', '3b', '4', or 'complete'.
     */
    public function getCurrentStep(User $user): string
    {
        // Step 1a: Experience
        if (! $user->experience_level) {
            return '1a';
        }

        // Step 1b: Risk
        if (! $user->risk_level) {
            return '1b';
        }

        // Step 2a: Goal
        if (! $user->investment_goal) {
            return '2a';
        }

        // Step 2b: Style
        if (! $user->trading_style) {
            return '2b';
        }

        // Step 3a: Country
        if (! $user->country_id) {
            return '3a';
        }

        // Step 3b: Markets
        if ($user->markets()->count() === 0) {
            return '3b';
        }

        // Step 4: Sectors
        if ($user->sectors()->count() === 0) {
            return '4';
        }

        // All complete
        return 'complete';
    }

    /**
     * Get selected values for a step from user data.
     */
    public function getSelectedValues(User $user, int $step): array
    {
        return match ($step) {
            1 => [
                'experience_level' => $user->experience_level,
                'risk_level' => $user->risk_level,
            ],
            2 => [
                'investment_goal' => $user->investment_goal,
                'trading_style' => $user->trading_style,
            ],
            3 => [
                'country_id' => $user->country_id,
                'markets' => $user->markets()->pluck('markets.id')->toArray(),
            ],
            4 => [
                'sectors' => $user->sectors()->pluck('sectors.id')->toArray(),
            ],
            default => [],
        };
    }
}
