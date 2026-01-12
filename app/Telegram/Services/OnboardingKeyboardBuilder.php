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
            $keyboard[] = [[
                'text' => $text,
                'callback_data' => "ob:exp:{$key}",
            ]];
        }

        return $keyboard;
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
            $keyboard[] = [[
                'text' => $text,
                'callback_data' => "ob:risk:{$key}",
            ]];
        }

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back',
            'callback_data' => 'ob:step1b:back',
        ]];

        return $keyboard;
    }

    /**
     * Build Step 2a keyboard: Investment Goal selection only.
     */
    public function buildStep2aKeyboard(string $locale, ?string $selectedGoal = null, int $goalPage = 1): array
    {
        $keyboard = [];

        // Investment goals (paginated, 5 per page for single column)
        $goals = array_keys(self::INVESTMENT_GOALS);
        $perPage = 5;
        $totalPages = (int) ceil(count($goals) / $perPage);
        $offset = ($goalPage - 1) * $perPage;
        $pageGoals = array_slice($goals, $offset, $perPage);

        // Goals in single column
        foreach ($pageGoals as $key) {
            $labels = self::INVESTMENT_GOALS[$key];
            $isSelected = $selectedGoal === $key;
            $text = $isSelected
                ? "✓ {$labels['icon']} {$labels[$locale]}"
                : "{$labels['icon']} {$labels[$locale]}";
            $keyboard[] = [[
                'text' => $text,
                'callback_data' => "ob:goal:{$key}",
            ]];
        }

        // Pagination for goals if needed
        if ($totalPages > 1) {
            $pageLabel = $locale === 'ar' ? "صفحة {$goalPage}/{$totalPages}" : "Page {$goalPage}/{$totalPages}";
            $navRow = [];
            if ($goalPage > 1) {
                $navRow[] = [
                    'text' => '◀️',
                    'callback_data' => 'ob:goal:page:'.($goalPage - 1),
                ];
            }
            $navRow[] = [
                'text' => $pageLabel,
                'callback_data' => 'ob:noop',
            ];
            if ($goalPage < $totalPages) {
                $navRow[] = [
                    'text' => '▶️',
                    'callback_data' => 'ob:goal:page:'.($goalPage + 1),
                ];
            }
            $keyboard[] = $navRow;
        }

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back',
            'callback_data' => 'ob:step2a:back',
        ]];

        return $keyboard;
    }

    /**
     * Build Step 2b keyboard: Trading Style selection only.
     */
    public function buildStep2bKeyboard(string $locale, ?string $selectedStyle = null): array
    {
        $keyboard = [];

        // Trading style buttons in single column
        foreach (self::TRADING_STYLES as $key => $labels) {
            $isSelected = $selectedStyle === $key;
            $text = $isSelected
                ? "✓ {$labels['icon']} {$labels[$locale]}"
                : "{$labels['icon']} {$labels[$locale]}";
            $keyboard[] = [[
                'text' => $text,
                'callback_data' => "ob:style:{$key}",
            ]];
        }

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back',
            'callback_data' => 'ob:step2b:back',
        ]];

        return $keyboard;
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

        // Countries in rows of 3
        $countryRows = $popularCountries->chunk(3);
        foreach ($countryRows as $row) {
            $btns = [];
            foreach ($row as $country) {
                $name = $locale === 'ar' ? $country->name_ar : $country->name_en;
                $flag = $flags[$country->code] ?? '🏳️';
                $isSelected = $selectedCountryId === $country->id;
                $text = $isSelected ? "✓ {$flag} {$name}" : "{$flag} {$name}";
                $btns[] = [
                    'text' => $text,
                    'callback_data' => "ob:country:{$country->id}",
                ];
            }
            $keyboard[] = $btns;
        }

        // Search button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '🔍 بحث عن دولة أخرى' : '🔍 Search other country',
            'callback_data' => 'ob:country:search',
        ]];

        // Back button - goes to step 2b (style)
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back',
            'callback_data' => 'ob:step3a:back',
        ]];

        return $keyboard;
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
            // No markets - show message and back
            $keyboard[] = [[
                'text' => $locale === 'ar' ? '⚠️ لا توجد أسواق متاحة' : '⚠️ No markets available',
                'callback_data' => 'ob:noop',
            ]];
            $keyboard[] = [[
                'text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back',
                'callback_data' => 'ob:step3:back',
            ]];

            return $keyboard;
        }

        // Paginate markets (6 per page)
        $perPage = 6;
        $totalPages = (int) ceil($markets->count() / $perPage);
        $offset = ($page - 1) * $perPage;
        $pageMarkets = $markets->slice($offset, $perPage);

        // Markets in rows of 3
        $marketRows = $pageMarkets->chunk(3);
        foreach ($marketRows as $row) {
            $btns = [];
            foreach ($row as $market) {
                $name = $locale === 'ar' ? ($market->name_ar ?: $market->name_en) : $market->name_en;
                $isSelected = in_array($market->id, $selectedMarketIds, true);
                $text = $isSelected ? "✅ {$name}" : "⬜ {$name}";
                $btns[] = [
                    'text' => $text,
                    'callback_data' => "ob:market:toggle:{$market->id}",
                ];
            }
            $keyboard[] = $btns;
        }

        // Pagination if needed
        if ($totalPages > 1) {
            $pageLabel = $locale === 'ar' ? "صفحة {$page}/{$totalPages}" : "Page {$page}/{$totalPages}";
            $navRow = [];
            if ($page > 1) {
                $navRow[] = [
                    'text' => '◀️',
                    'callback_data' => 'ob:market:page:'.($page - 1),
                ];
            }
            $navRow[] = [
                'text' => $pageLabel,
                'callback_data' => 'ob:noop',
            ];
            if ($page < $totalPages) {
                $navRow[] = [
                    'text' => '▶️',
                    'callback_data' => 'ob:market:page:'.($page + 1),
                ];
            }
            $keyboard[] = $navRow;
        }

        // Selected count
        $selectedCount = count($selectedMarketIds);
        $countText = $locale === 'ar'
            ? "📊 تم اختيار: {$selectedCount} سوق"
            : "📊 Selected: {$selectedCount} market(s)";

        // Change country / Back / Next
        $keyboard[] = [[
            'text' => $countText,
            'callback_data' => 'ob:noop',
        ]];

        $navRow = [];
        $navRow[] = [
            'text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back',
            'callback_data' => 'ob:step3b:back',
        ];
        if ($selectedCount > 0) {
            $navRow[] = [
                'text' => $locale === 'ar' ? '➡️ التالي' : '➡️ Next',
                'callback_data' => 'ob:step3:next',
            ];
        }
        $keyboard[] = $navRow;

        return $keyboard;
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
            $keyboard[] = [[
                'text' => $locale === 'ar' ? '⚠️ لا توجد قطاعات متاحة' : '⚠️ No sectors available',
                'callback_data' => 'ob:noop',
            ]];
            $keyboard[] = [[
                'text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back',
                'callback_data' => 'ob:step4:back',
            ]];

            return $keyboard;
        }

        // Paginate sectors (6 per page)
        $perPage = 6;
        $totalPages = (int) ceil($sectors->count() / $perPage);
        $offset = ($page - 1) * $perPage;
        $pageSectors = $sectors->slice($offset, $perPage);

        // Sectors in rows of 3
        $sectorRows = $pageSectors->chunk(3);
        foreach ($sectorRows as $row) {
            $btns = [];
            foreach ($row as $sector) {
                $name = $locale === 'ar' ? ($sector->name_ar ?: $sector->name_en) : $sector->name_en;
                $isSelected = in_array($sector->id, $selectedSectorIds, true);
                $text = $isSelected ? "✅ {$name}" : "⬜ {$name}";
                $btns[] = [
                    'text' => $text,
                    'callback_data' => "ob:sector:toggle:{$sector->id}",
                ];
            }
            $keyboard[] = $btns;
        }

        // Pagination if needed
        if ($totalPages > 1) {
            $pageLabel = $locale === 'ar' ? "صفحة {$page}/{$totalPages}" : "Page {$page}/{$totalPages}";
            $navRow = [];
            if ($page > 1) {
                $navRow[] = [
                    'text' => '◀️',
                    'callback_data' => 'ob:sector:page:'.($page - 1),
                ];
            }
            $navRow[] = [
                'text' => $pageLabel,
                'callback_data' => 'ob:noop',
            ];
            if ($page < $totalPages) {
                $navRow[] = [
                    'text' => '▶️',
                    'callback_data' => 'ob:sector:page:'.($page + 1),
                ];
            }
            $keyboard[] = $navRow;
        }

        // Selected count
        $selectedCount = count($selectedSectorIds);
        $countText = $locale === 'ar'
            ? "🏭 تم اختيار: {$selectedCount} قطاع"
            : "🏭 Selected: {$selectedCount} sector(s)";

        $keyboard[] = [[
            'text' => $countText,
            'callback_data' => 'ob:noop',
        ]];

        // Back / Complete
        $navRow = [];
        $navRow[] = [
            'text' => $locale === 'ar' ? '⬅️ السابق' : '⬅️ Back',
            'callback_data' => 'ob:step4:back',
        ];
        if ($selectedCount > 0) {
            $navRow[] = [
                'text' => $locale === 'ar' ? '✅ إكمال' : '✅ Complete',
                'callback_data' => 'ob:complete',
            ];
        }
        $keyboard[] = $navRow;

        return $keyboard;
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
     * Build completion keyboard with Mini App button.
     */
    public function buildCompletionKeyboard(string $locale): array
    {
        return [[
            [
                'text' => $locale === 'ar' ? '📊 فتح لوحة التحكم' : '📊 Open Dashboard',
                'web_app' => ['url' => route('dashboard')],
            ],
        ]];
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
