<?php

namespace App\Telegram\Services;

use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use App\Models\User;

class SettingsKeyboardBuilder
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
        'capital_growth' => ['en' => 'Capital Growth', 'ar' => 'نمو رأس المال', 'icon' => '📈'],
        'fixed_income' => ['en' => 'Fixed Income', 'ar' => 'دخل ثابت', 'icon' => '💰'],
        'risk_reduction' => ['en' => 'Risk Reduction', 'ar' => 'تقليل المخاطر', 'icon' => '🛡️'],
        'short_term_speculation' => ['en' => 'Speculation', 'ar' => 'مضاربة', 'icon' => '⚡'],
        'retirement_planning' => ['en' => 'Retirement', 'ar' => 'تقاعد', 'icon' => '🏖️'],
        'wealth_preservation' => ['en' => 'Wealth Preservation', 'ar' => 'حفظ الثروة', 'icon' => '🏛️'],
        'passive_income' => ['en' => 'Passive Income', 'ar' => 'دخل سلبي', 'icon' => '💸'],
        'education_savings' => ['en' => 'Education', 'ar' => 'تعليم', 'icon' => '🎓'],
        'home_purchase' => ['en' => 'Home Purchase', 'ar' => 'شراء منزل', 'icon' => '🏠'],
        'emergency_fund' => ['en' => 'Emergency Fund', 'ar' => 'طوارئ', 'icon' => '🆘'],
    ];

    /**
     * Trading style options with icons.
     */
    private const TRADING_STYLES = [
        'day_trading' => ['en' => 'Day Trading', 'ar' => 'يومي', 'icon' => '📊'],
        'swing_trading' => ['en' => 'Swing Trading', 'ar' => 'متأرجح', 'icon' => '🔄'],
        'position_trading' => ['en' => 'Position Trading', 'ar' => 'مركز', 'icon' => '📅'],
        'scalping_trading' => ['en' => 'Scalping', 'ar' => 'سريع', 'icon' => '⚡'],
    ];

    /**
     * Notification channels.
     */
    private const CHANNELS = [
        'telegram' => ['en' => 'Telegram', 'ar' => 'تيليجرام'],
        'email' => ['en' => 'Email', 'ar' => 'البريد الإلكتروني'],
        'push' => ['en' => 'Push Notifications', 'ar' => 'إشعارات الهاتف'],
        'in_app' => ['en' => 'In-App', 'ar' => 'داخل التطبيق'],
    ];

    /**
     * Popular countries by code for quick selection.
     */
    private const POPULAR_COUNTRY_CODES = ['EG', 'SA'];

    /**
     * Country flags by code.
     */
    private const COUNTRY_FLAGS = [
        'EG' => '🇪🇬',
        'SA' => '🇸🇦',
    ];

    // ==========================================
    // Main Settings Menu
    // ==========================================

    /**
     * Build main settings menu keyboard.
     */
    public function buildMainMenu(string $locale): array
    {
        return [
            [
                [
                    'text' => $locale === 'ar' ? '👤 الملف الشخصي' : '👤 Profile',
                    'callback_data' => 'set:profile',
                ],
                [
                    'text' => $locale === 'ar' ? '📊 التداول' : '📊 Trading',
                    'callback_data' => 'set:trading',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '🌍 الأسواق' : '🌍 Markets',
                    'callback_data' => 'set:markets',
                ],
                [
                    'text' => $locale === 'ar' ? '🔔 التنبيهات' : '🔔 Alerts',
                    'callback_data' => 'set:alerts',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '🌐 اللغة' : '🌐 Language',
                    'callback_data' => 'set:language',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '📱 فتح التطبيق' : '📱 Open App',
                    'web_app' => ['url' => route('profile.edit')],
                ],
            ],
        ];
    }

    /**
     * Build language selector keyboard.
     */
    public function buildLanguageSelector(string $locale): array
    {
        return [
            [
                [
                    'text' => $locale === 'en' ? '✓ 🇬🇧 English' : '🇬🇧 English',
                    'callback_data' => 'lang:en',
                ],
                [
                    'text' => $locale === 'ar' ? '✓ 🇸🇦 العربية' : '🇸🇦 العربية',
                    'callback_data' => 'lang:ar',
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                    'callback_data' => 'set:menu',
                ],
            ],
        ];
    }

    /**
     * Build language change confirmation keyboard.
     */
    public function buildLanguageConfirmation(string $locale): array
    {
        return [
            [
                [
                    'text' => $locale === 'ar' ? '📊 فتح حورين' : '📊 Open Horin',
                    'web_app' => ['url' => route('dashboard')],
                ],
            ],
            [
                [
                    'text' => $locale === 'ar' ? '⚙️ الإعدادات' : '⚙️ Settings',
                    'callback_data' => 'set:menu',
                ],
            ],
        ];
    }

    // ==========================================
    // Profile Settings
    // ==========================================

    /**
     * Build profile settings keyboard.
     */
    public function buildProfileMenu(User $user, string $locale): array
    {
        $name = $user->name ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
        $langDisplay = $locale === 'ar' ? 'العربية' : 'English';

        return [
            [[
                'text' => $locale === 'ar' ? "📛 الاسم: {$name}" : "📛 Name: {$name}",
                'callback_data' => 'set:profile:name',
            ]],
            [[
                'text' => $locale === 'ar' ? "🌐 اللغة: {$langDisplay}" : "🌐 Language: {$langDisplay}",
                'callback_data' => 'set:language',
            ]],
            [[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'set:menu',
            ]],
        ];
    }

    /**
     * Build cancel keyboard for text input prompts.
     */
    public function buildCancelButton(string $callbackData, string $locale): array
    {
        return [[
            [
                'text' => $locale === 'ar' ? '⬅️ إلغاء' : '⬅️ Cancel',
                'callback_data' => $callbackData,
            ],
        ]];
    }

    // ==========================================
    // Trading Profile Settings
    // ==========================================

    /**
     * Build trading settings menu keyboard.
     */
    public function buildTradingMenu(User $user, string $locale): array
    {
        $exp = self::EXPERIENCE_LEVELS[$user->experience_level][$locale] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
        $risk = self::RISK_LEVELS[$user->risk_level][$locale] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
        $goal = self::INVESTMENT_GOALS[$user->investment_goal][$locale] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
        $style = self::TRADING_STYLES[$user->trading_style][$locale] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');

        return [
            [[
                'text' => $locale === 'ar' ? "🎯 الخبرة: {$exp}" : "🎯 Experience: {$exp}",
                'callback_data' => 'set:trading:exp:select',
            ]],
            [[
                'text' => $locale === 'ar' ? "⚖️ المخاطر: {$risk}" : "⚖️ Risk: {$risk}",
                'callback_data' => 'set:trading:risk:select',
            ]],
            [[
                'text' => $locale === 'ar' ? "📈 الهدف: {$goal}" : "📈 Goal: {$goal}",
                'callback_data' => 'set:trading:goal:select',
            ]],
            [[
                'text' => $locale === 'ar' ? "📊 الأسلوب: {$style}" : "📊 Style: {$style}",
                'callback_data' => 'set:trading:style:select',
            ]],
            [[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'set:menu',
            ]],
        ];
    }

    /**
     * Build experience selector keyboard.
     */
    public function buildExperienceSelector(?string $selectedLevel, string $locale): array
    {
        $keyboard = [];

        foreach (self::EXPERIENCE_LEVELS as $level => $labels) {
            $isSelected = $selectedLevel === $level;
            $text = $isSelected
                ? "✓ {$labels['icon']} {$labels[$locale]}"
                : "{$labels['icon']} {$labels[$locale]}";

            $keyboard[] = [[
                'text' => $text,
                'callback_data' => "set:trading:exp:{$level}",
            ]];
        }

        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:trading',
        ]];

        return $keyboard;
    }

    /**
     * Build risk selector keyboard.
     */
    public function buildRiskSelector(?string $selectedLevel, string $locale): array
    {
        $keyboard = [];

        foreach (self::RISK_LEVELS as $level => $labels) {
            $isSelected = $selectedLevel === $level;
            $text = $isSelected
                ? "✓ {$labels['icon']} {$labels[$locale]}"
                : "{$labels['icon']} {$labels[$locale]}";

            $keyboard[] = [[
                'text' => $text,
                'callback_data' => "set:trading:risk:{$level}",
            ]];
        }

        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:trading',
        ]];

        return $keyboard;
    }

    /**
     * Build investment goal selector keyboard with pagination.
     */
    public function buildGoalSelector(?string $selectedGoal, string $locale, int $page = 1): array
    {
        $goals = array_keys(self::INVESTMENT_GOALS);
        $perPage = 6;
        $totalPages = (int) ceil(count($goals) / $perPage);
        $offset = ($page - 1) * $perPage;
        $pageGoals = array_slice($goals, $offset, $perPage);

        $keyboard = [];

        // Goals in rows of 2
        $rows = array_chunk($pageGoals, 2);
        foreach ($rows as $row) {
            $btns = [];
            foreach ($row as $goal) {
                $labels = self::INVESTMENT_GOALS[$goal];
                $isSelected = $selectedGoal === $goal;
                $text = $isSelected
                    ? "✓ {$labels['icon']} {$labels[$locale]}"
                    : "{$labels['icon']} {$labels[$locale]}";

                $btns[] = [
                    'text' => $text,
                    'callback_data' => "set:trading:goal:{$goal}",
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
                    'callback_data' => 'set:trading:goal:page:'.($page - 1),
                ];
            }
            $navRow[] = [
                'text' => "{$page}/{$totalPages}",
                'callback_data' => 'noop',
            ];
            if ($page < $totalPages) {
                $navRow[] = [
                    'text' => '▶️',
                    'callback_data' => 'set:trading:goal:page:'.($page + 1),
                ];
            }
            $keyboard[] = $navRow;
        }

        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:trading',
        ]];

        return $keyboard;
    }

    /**
     * Build trading style selector keyboard.
     */
    public function buildStyleSelector(?string $selectedStyle, string $locale): array
    {
        $keyboard = [];

        foreach (self::TRADING_STYLES as $style => $labels) {
            $isSelected = $selectedStyle === $style;
            $text = $isSelected
                ? "✓ {$labels['icon']} {$labels[$locale]}"
                : "{$labels['icon']} {$labels[$locale]}";

            $keyboard[] = [[
                'text' => $text,
                'callback_data' => "set:trading:style:{$style}",
            ]];
        }

        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:trading',
        ]];

        return $keyboard;
    }

    // ==========================================
    // Alert Preferences Settings
    // ==========================================

    /**
     * Build alert preferences menu keyboard.
     */
    public function buildAlertPreferencesMenu(User $user, string $locale): array
    {
        $prefs = $user->getAlertPreferences();
        $channels = $prefs->channels ?? ['telegram', 'in_app'];
        $maxHour = $prefs->max_alerts_per_hour ?? 10;
        $maxDay = $prefs->max_alerts_per_day ?? 25;
        $channelCount = count($channels);

        return [
            [[
                'text' => $locale === 'ar'
                    ? "📱 قنوات الإشعارات ({$channelCount})"
                    : "📱 Notification Channels ({$channelCount})",
                'callback_data' => 'set:alerts:channels:select',
            ]],
            [[
                'text' => $locale === 'ar'
                    ? "⏰ الحدود: {$maxHour}/ساعة، {$maxDay}/يوم"
                    : "⏰ Limits: {$maxHour}/hr, {$maxDay}/day",
                'callback_data' => 'set:alerts:limits:select',
            ]],
            [[
                'text' => $locale === 'ar' ? '⚙️ إعدادات متقدمة' : '⚙️ Advanced Settings',
                'web_app' => ['url' => route('settings.alerts')],
            ]],
            [[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'set:menu',
            ]],
        ];
    }

    /**
     * Build notification channels selector keyboard.
     */
    public function buildChannelsSelector(array $enabledChannels, string $locale): array
    {
        $keyboard = [];

        foreach (self::CHANNELS as $channel => $labels) {
            $isEnabled = in_array($channel, $enabledChannels, true);
            $text = ($isEnabled ? '✅' : '⬜').' '.$labels[$locale];

            $keyboard[] = [[
                'text' => $text,
                'callback_data' => "set:alerts:channel:{$channel}",
            ]];
        }

        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:alerts',
        ]];

        return $keyboard;
    }

    /**
     * Build alert limits selector keyboard.
     */
    public function buildLimitsSelector(int $maxHour, int $maxDay, string $locale): array
    {
        return [
            // Max per hour controls
            [
                [
                    'text' => '➖',
                    'callback_data' => 'set:alerts:max:hour:'.max(1, $maxHour - 5),
                ],
                [
                    'text' => $locale === 'ar' ? "⏰ {$maxHour}/ساعة" : "⏰ {$maxHour}/hr",
                    'callback_data' => 'noop',
                ],
                [
                    'text' => '➕',
                    'callback_data' => 'set:alerts:max:hour:'.min(50, $maxHour + 5),
                ],
            ],
            // Max per day controls
            [
                [
                    'text' => '➖',
                    'callback_data' => 'set:alerts:max:day:'.max(5, $maxDay - 10),
                ],
                [
                    'text' => $locale === 'ar' ? "📅 {$maxDay}/يوم" : "📅 {$maxDay}/day",
                    'callback_data' => 'noop',
                ],
                [
                    'text' => '➕',
                    'callback_data' => 'set:alerts:max:day:'.min(100, $maxDay + 10),
                ],
            ],
            [[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'set:alerts',
            ]],
        ];
    }

    // ==========================================
    // Market Preferences Settings
    // ==========================================

    /**
     * Build market preferences menu keyboard.
     */
    public function buildMarketPreferencesMenu(User $user, string $locale): array
    {
        $country = $user->country;
        $countryName = $country
            ? ($locale === 'ar' ? $country->name_ar : $country->name_en)
            : ($locale === 'ar' ? 'غير محدد' : 'Not set');

        $marketsCount = $user->markets()->count();
        $sectorsCount = $user->sectors()->count();

        return [
            [[
                'text' => $locale === 'ar' ? "🏳️ الدولة: {$countryName}" : "🏳️ Country: {$countryName}",
                'callback_data' => 'set:markets:country',
            ]],
            [[
                'text' => $locale === 'ar' ? "📊 الأسواق ({$marketsCount})" : "📊 Markets ({$marketsCount})",
                'callback_data' => 'set:markets:market:page:1',
            ]],
            [[
                'text' => $locale === 'ar' ? "🏭 القطاعات ({$sectorsCount})" : "🏭 Sectors ({$sectorsCount})",
                'callback_data' => 'set:markets:sectors',
            ]],
            [[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'set:menu',
            ]],
        ];
    }

    /**
     * Build country selector keyboard.
     */
    public function buildCountrySelector(?string $selectedCountryId, string $locale): array
    {
        // Use CASE WHEN for PostgreSQL-compatible ordering
        $orderCase = 'CASE ';
        foreach (self::POPULAR_COUNTRY_CODES as $index => $code) {
            $orderCase .= "WHEN code = '{$code}' THEN {$index} ";
        }
        $orderCase .= 'END';

        $popularCountries = Country::whereIn('code', self::POPULAR_COUNTRY_CODES)
            ->orderByRaw($orderCase)
            ->get();

        $keyboard = [];

        // Countries in rows of 2
        $rows = $popularCountries->chunk(2);
        foreach ($rows as $row) {
            $btns = [];
            foreach ($row as $country) {
                $name = $locale === 'ar' ? $country->name_ar : $country->name_en;
                $flag = self::COUNTRY_FLAGS[$country->code] ?? '🏳️';
                $isSelected = $selectedCountryId === $country->id;
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

        return $keyboard;
    }

    /**
     * Build market selector keyboard with pagination.
     */
    public function buildMarketSelector(string $countryId, array $selectedMarketIds, string $locale, int $page = 1): array
    {
        $markets = Market::where('country_id', $countryId)->get();

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

        return $keyboard;
    }

    /**
     * Build sector selector keyboard with pagination.
     */
    public function buildSectorSelector(array $selectedSectorIds, string $locale, int $page = 1): array
    {
        $sectors = Sector::all();

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
                        'callback_data' => 'set:markets:sector:page:'.($page - 1),
                    ];
                }
                $navRow[] = [
                    'text' => "{$page}/{$totalPages}",
                    'callback_data' => 'noop',
                ];
                if ($page < $totalPages) {
                    $navRow[] = [
                        'text' => '▶️',
                        'callback_data' => 'set:markets:sector:page:'.($page + 1),
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

        return $keyboard;
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * Build a simple back button keyboard.
     */
    public function buildBackButton(string $callbackData, string $locale): array
    {
        return [[
            [
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => $callbackData,
            ],
        ]];
    }

    /**
     * Get experience level label.
     */
    public function getExperienceLabel(?string $level, string $locale): string
    {
        return self::EXPERIENCE_LEVELS[$level][$locale] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
    }

    /**
     * Get risk level label.
     */
    public function getRiskLabel(?string $level, string $locale): string
    {
        return self::RISK_LEVELS[$level][$locale] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
    }

    /**
     * Get investment goal label.
     */
    public function getGoalLabel(?string $goal, string $locale): string
    {
        return self::INVESTMENT_GOALS[$goal][$locale] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
    }

    /**
     * Get trading style label.
     */
    public function getStyleLabel(?string $style, string $locale): string
    {
        return self::TRADING_STYLES[$style][$locale] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
    }

    /**
     * Get channel label.
     */
    public function getChannelLabel(string $channel, string $locale): string
    {
        return self::CHANNELS[$channel][$locale] ?? $channel;
    }
}
