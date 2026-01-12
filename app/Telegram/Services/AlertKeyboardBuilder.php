<?php

namespace App\Telegram\Services;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Collection;

class AlertKeyboardBuilder
{
    /**
     * Alert type options with icons.
     */
    private const ALERT_TYPES = [
        'price' => ['en' => 'Price Alert', 'ar' => 'تنبيه السعر', 'icon' => '💰'],
        'signal' => ['en' => 'Signal Alert', 'ar' => 'تنبيه إشارة', 'icon' => '📈'],
        'prediction' => ['en' => 'Prediction Alert', 'ar' => 'تنبيه توقع', 'icon' => '🔮'],
    ];

    /**
     * Trigger type options with icons.
     */
    private const TRIGGER_TYPES = [
        'target_price' => ['en' => 'Target Price', 'ar' => 'سعر مستهدف', 'icon' => '🎯'],
        'daily_change' => ['en' => 'Daily Change %', 'ar' => 'تغير يومي %', 'icon' => '📊'],
        'breakout' => ['en' => 'Breakout Level', 'ar' => 'مستوى اختراق', 'icon' => '📈'],
    ];

    /**
     * Direction options with icons.
     */
    private const DIRECTIONS = [
        'above' => ['en' => 'Above', 'ar' => 'أعلى من', 'icon' => '⬆️'],
        'below' => ['en' => 'Below', 'ar' => 'أقل من', 'icon' => '⬇️'],
        'both' => ['en' => 'Either Direction', 'ar' => 'أي اتجاه', 'icon' => '↕️'],
    ];

    /**
     * Snooze presets.
     */
    private const SNOOZE_PRESETS = [
        '1h' => ['en' => '1 Hour', 'ar' => 'ساعة واحدة', 'minutes' => 60],
        '4h' => ['en' => '4 Hours', 'ar' => '4 ساعات', 'minutes' => 240],
        '1d' => ['en' => '1 Day', 'ar' => 'يوم واحد', 'minutes' => 1440],
        'until_market_close' => ['en' => 'Until Market Close', 'ar' => 'حتى إغلاق السوق', 'minutes' => null],
        'until_market_open' => ['en' => 'Until Market Open', 'ar' => 'حتى فتح السوق', 'minutes' => null],
    ];

    /**
     * Build main alerts menu keyboard.
     */
    public function buildMainMenu(User $user, string $locale): array
    {
        $activeCount = Alert::where('user_id', $user->id)->active()->count();
        $triggeredToday = $this->getTriggeredTodayCount($user);

        $keyboard = [];

        // Create alert button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '➕ إنشاء تنبيه' : '➕ Create Alert',
            'callback_data' => 'alert:create',
        ]];

        // Active alerts button
        $activeLabel = $locale === 'ar'
            ? "📊 التنبيهات النشطة ({$activeCount})"
            : "📊 Active Alerts ({$activeCount})";
        $keyboard[] = [[
            'text' => $activeLabel,
            'callback_data' => 'alert:list',
        ]];

        // Triggered today button (if any)
        if ($triggeredToday > 0) {
            $triggeredLabel = $locale === 'ar'
                ? "🔔 تم تفعيلها اليوم ({$triggeredToday})"
                : "🔔 Triggered Today ({$triggeredToday})";
            $keyboard[] = [[
                'text' => $triggeredLabel,
                'callback_data' => 'alert:list:triggered',
            ]];
        }

        // History button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '📜 سجل التنبيهات' : '📜 Alert History',
            'callback_data' => 'alert:history',
        ]];

        return $keyboard;
    }

    /**
     * Build alert type selector keyboard.
     */
    public function buildAlertTypeSelector(string $locale): array
    {
        $keyboard = [];

        foreach (self::ALERT_TYPES as $type => $labels) {
            $keyboard[] = [[
                'text' => "{$labels['icon']} {$labels[$locale]}",
                'callback_data' => "alert:create:type:{$type}",
            ]];
        }

        // Cancel button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ إلغاء' : '⬅️ Cancel',
            'callback_data' => 'alert:menu',
        ]];

        return $keyboard;
    }

    /**
     * Build asset selector keyboard from user's portfolio and watchlist.
     *
     * @param  Collection<Asset>  $portfolioAssets
     * @param  Collection<Asset>  $watchlistAssets
     */
    public function buildAssetSelector(
        Collection $portfolioAssets,
        Collection $watchlistAssets,
        string $locale
    ): array {
        $keyboard = [];

        // Portfolio assets (up to 6)
        if ($portfolioAssets->isNotEmpty()) {
            $portfolioChunks = $portfolioAssets->take(6)->chunk(3);
            foreach ($portfolioChunks as $chunk) {
                $row = [];
                foreach ($chunk as $asset) {
                    $row[] = [
                        'text' => $asset->symbol,
                        'callback_data' => "alert:create:asset:{$asset->id}",
                    ];
                }
                $keyboard[] = $row;
            }
        }

        // Watchlist assets (up to 6)
        if ($watchlistAssets->isNotEmpty()) {
            $watchlistChunks = $watchlistAssets->take(6)->chunk(3);
            foreach ($watchlistChunks as $chunk) {
                $row = [];
                foreach ($chunk as $asset) {
                    $row[] = [
                        'text' => "⭐ {$asset->symbol}",
                        'callback_data' => "alert:create:asset:{$asset->id}",
                    ];
                }
                $keyboard[] = $row;
            }
        }

        // Search button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '🔍 بحث عن أصل' : '🔍 Search Asset',
            'callback_data' => 'alert:create:asset:search',
        ]];

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'alert:create',
        ]];

        return $keyboard;
    }

    /**
     * Build asset search results keyboard.
     *
     * @param  Collection<Asset>  $results
     */
    public function buildAssetSearchResults(Collection $results, string $locale): array
    {
        $keyboard = [];

        if ($results->isEmpty()) {
            $keyboard[] = [[
                'text' => $locale === 'ar' ? '❌ لم يتم العثور على نتائج' : '❌ No results found',
                'callback_data' => 'noop',
            ]];
        } else {
            foreach ($results->take(5) as $asset) {
                $name = $locale === 'ar' ? ($asset->name_ar ?: $asset->name) : $asset->name;
                $keyboard[] = [[
                    'text' => "{$asset->symbol} - {$name}",
                    'callback_data' => "alert:create:asset:{$asset->id}",
                ]];
            }
        }

        // Back/Cancel button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'alert:create:type:price',
        ]];

        return $keyboard;
    }

    /**
     * Build trigger type selector keyboard.
     */
    public function buildTriggerTypeSelector(string $alertType, string $locale): array
    {
        $keyboard = [];

        // Only show relevant trigger types for the alert type
        $triggerTypes = match ($alertType) {
            'price' => ['target_price', 'daily_change', 'breakout'],
            'signal' => ['signal'],
            'prediction' => ['prediction'],
            default => ['target_price'],
        };

        foreach ($triggerTypes as $type) {
            if (isset(self::TRIGGER_TYPES[$type])) {
                $labels = self::TRIGGER_TYPES[$type];
                $keyboard[] = [[
                    'text' => "{$labels['icon']} {$labels[$locale]}",
                    'callback_data' => "alert:create:trigger:{$type}",
                ]];
            }
        }

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'alert:create:type:price',
        ]];

        return $keyboard;
    }

    /**
     * Build direction selector keyboard.
     */
    public function buildDirectionSelector(float $targetValue, string $locale): array
    {
        $keyboard = [];

        foreach (self::DIRECTIONS as $direction => $labels) {
            $text = "{$labels['icon']} {$labels[$locale]}";
            if ($direction !== 'both') {
                $text .= " {$targetValue}";
            }
            $keyboard[] = [[
                'text' => $text,
                'callback_data' => "alert:create:direction:{$direction}",
            ]];
        }

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'alert:create:trigger:target_price',
        ]];

        return $keyboard;
    }

    /**
     * Build confirmation keyboard.
     */
    public function buildConfirmation(string $locale): array
    {
        return [
            [[
                'text' => $locale === 'ar' ? '✅ إنشاء التنبيه' : '✅ Create Alert',
                'callback_data' => 'alert:create:confirm',
            ]],
            [[
                'text' => $locale === 'ar' ? '⬅️ إلغاء' : '⬅️ Cancel',
                'callback_data' => 'alert:menu',
            ]],
        ];
    }

    /**
     * Build active alerts list keyboard with pagination.
     *
     * @param  Collection<Alert>  $alerts
     */
    public function buildActiveAlertsList(Collection $alerts, int $page, int $totalPages, string $locale): array
    {
        $keyboard = [];

        // Alert buttons (numbered)
        $startIndex = ($page - 1) * 5;
        $row = [];
        foreach ($alerts as $index => $alert) {
            $num = $startIndex + $index + 1;
            $row[] = [
                'text' => (string) $num,
                'callback_data' => "alert:view:{$alert->id}",
            ];

            // Max 5 per row
            if (count($row) >= 5) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        if (! empty($row)) {
            $keyboard[] = $row;
        }

        // Pagination
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 1) {
                $navRow[] = [
                    'text' => '◀️',
                    'callback_data' => 'alert:list:page:'.($page - 1),
                ];
            }
            $navRow[] = [
                'text' => "{$page}/{$totalPages}",
                'callback_data' => 'noop',
            ];
            if ($page < $totalPages) {
                $navRow[] = [
                    'text' => '▶️',
                    'callback_data' => 'alert:list:page:'.($page + 1),
                ];
            }
            $keyboard[] = $navRow;
        }

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'alert:menu',
        ]];

        return $keyboard;
    }

    /**
     * Build alert detail actions keyboard.
     */
    public function buildAlertActions(Alert $alert, string $locale): array
    {
        $keyboard = [];

        // Status actions
        $statusRow = [];
        if ($alert->status === 'active') {
            $statusRow[] = [
                'text' => $locale === 'ar' ? '⏸️ إيقاف مؤقت' : '⏸️ Pause',
                'callback_data' => "alert:toggle:{$alert->id}",
            ];
        } else {
            $statusRow[] = [
                'text' => $locale === 'ar' ? '▶️ تفعيل' : '▶️ Resume',
                'callback_data' => "alert:toggle:{$alert->id}",
            ];
        }

        // Snooze button (only if active and not snoozed)
        if ($alert->status === 'active' && ! $alert->snoozed_until) {
            $statusRow[] = [
                'text' => $locale === 'ar' ? '😴 تأجيل' : '😴 Snooze',
                'callback_data' => "alert:snooze:{$alert->id}",
            ];
        } elseif ($alert->snoozed_until) {
            $statusRow[] = [
                'text' => $locale === 'ar' ? '⏰ إلغاء التأجيل' : '⏰ Unsnooze',
                'callback_data' => "alert:unsnooze:{$alert->id}",
            ];
        }
        $keyboard[] = $statusRow;

        // Delete button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '🗑️ حذف' : '🗑️ Delete',
            'callback_data' => "alert:delete:{$alert->id}",
        ]];

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع للقائمة' : '⬅️ Back to List',
            'callback_data' => 'alert:list',
        ]];

        return $keyboard;
    }

    /**
     * Build snooze options keyboard.
     */
    public function buildSnoozeOptions(string $alertId, string $locale): array
    {
        $keyboard = [];

        // First row: 1h, 4h, 1d
        $row1 = [];
        foreach (['1h', '4h', '1d'] as $preset) {
            $row1[] = [
                'text' => self::SNOOZE_PRESETS[$preset][$locale],
                'callback_data' => "alert:snooze:{$alertId}:{$preset}",
            ];
        }
        $keyboard[] = $row1;

        // Market-based options
        $keyboard[] = [[
            'text' => self::SNOOZE_PRESETS['until_market_close'][$locale],
            'callback_data' => "alert:snooze:{$alertId}:until_market_close",
        ]];
        $keyboard[] = [[
            'text' => self::SNOOZE_PRESETS['until_market_open'][$locale],
            'callback_data' => "alert:snooze:{$alertId}:until_market_open",
        ]];

        // Cancel button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ إلغاء' : '⬅️ Cancel',
            'callback_data' => "alert:view:{$alertId}",
        ]];

        return $keyboard;
    }

    /**
     * Build delete confirmation keyboard.
     */
    public function buildDeleteConfirmation(string $alertId, string $locale): array
    {
        return [
            [[
                'text' => $locale === 'ar' ? '🗑️ نعم، حذف' : '🗑️ Yes, Delete',
                'callback_data' => "alert:delete:{$alertId}:confirm",
            ]],
            [[
                'text' => $locale === 'ar' ? '⬅️ إلغاء' : '⬅️ Cancel',
                'callback_data' => "alert:view:{$alertId}",
            ]],
        ];
    }

    /**
     * Build alert history list keyboard.
     *
     * @param  Collection  $history
     */
    public function buildHistoryList(Collection $history, int $page, int $totalPages, string $locale): array
    {
        $keyboard = [];

        // Acknowledge buttons for unacknowledged items
        foreach ($history as $item) {
            if (! $item->acknowledged_at) {
                $keyboard[] = [[
                    'text' => $locale === 'ar' ? '👁️ قراءة' : '👁️ Acknowledge',
                    'callback_data' => "alert:history:ack:{$item->id}",
                ]];
            }
        }

        // Pagination
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 1) {
                $navRow[] = [
                    'text' => '◀️',
                    'callback_data' => 'alert:history:page:'.($page - 1),
                ];
            }
            $navRow[] = [
                'text' => "{$page}/{$totalPages}",
                'callback_data' => 'noop',
            ];
            if ($page < $totalPages) {
                $navRow[] = [
                    'text' => '▶️',
                    'callback_data' => 'alert:history:page:'.($page + 1),
                ];
            }
            $keyboard[] = $navRow;
        }

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'alert:menu',
        ]];

        return $keyboard;
    }

    /**
     * Build success message keyboard after alert creation.
     */
    public function buildCreationSuccess(string $locale): array
    {
        return [
            [
                [
                    'text' => $locale === 'ar' ? '📊 عرض التنبيهات' : '📊 View Alerts',
                    'callback_data' => 'alert:list',
                ],
                [
                    'text' => $locale === 'ar' ? '➕ إنشاء آخر' : '➕ Create Another',
                    'callback_data' => 'alert:create',
                ],
            ],
        ];
    }

    /**
     * Format alert for display in list.
     */
    public function formatAlertLine(Alert $alert, string $locale): string
    {
        $symbol = $alert->asset?->symbol ?? 'N/A';
        $icon = $this->getTriggerIcon($alert->trigger_type);
        $direction = $this->getDirectionIcon($alert->direction);

        $value = '';
        if ($alert->trigger_type === 'target_price' && isset($alert->parameters['target_price'])) {
            $value = number_format($alert->parameters['target_price'], 2);
        } elseif ($alert->trigger_type === 'daily_change' && isset($alert->parameters['threshold_percent'])) {
            $value = $alert->parameters['threshold_percent'].'%';
        }

        return "{$symbol} {$icon} {$value} {$direction}";
    }

    /**
     * Format alert details for full view.
     */
    public function formatAlertDetails(Alert $alert, string $locale): string
    {
        $asset = $alert->asset;
        $symbol = $asset?->symbol ?? 'N/A';
        $name = $asset ? ($locale === 'ar' ? ($asset->name_ar ?: $asset->name) : $asset->name) : '';
        $currentPrice = $asset?->last_price ? number_format($asset->last_price, 2) : 'N/A';
        $currency = $asset?->market?->country?->currency_code ?? 'EGP';

        $triggerLabel = self::TRIGGER_TYPES[$alert->trigger_type][$locale] ?? $alert->trigger_type;
        $triggerIcon = $this->getTriggerIcon($alert->trigger_type);
        $directionLabel = self::DIRECTIONS[$alert->direction][$locale] ?? $alert->direction;
        $directionIcon = $this->getDirectionIcon($alert->direction);

        // Build parameter display
        $paramDisplay = '';
        if ($alert->trigger_type === 'target_price' && isset($alert->parameters['target_price'])) {
            $target = number_format($alert->parameters['target_price'], 2);
            $paramDisplay = $locale === 'ar'
                ? "💰 السعر المستهدف: {$target} {$currency}"
                : "💰 Target: {$target} {$currency}";
        } elseif ($alert->trigger_type === 'daily_change' && isset($alert->parameters['threshold_percent'])) {
            $pct = $alert->parameters['threshold_percent'];
            $paramDisplay = $locale === 'ar'
                ? "📊 التغير: {$pct}%"
                : "📊 Change: {$pct}%";
        }

        // Status
        $statusIcon = match ($alert->status) {
            'active' => '✅',
            'paused' => '⏸️',
            'triggered' => '🔔',
            default => '❓',
        };
        $statusLabel = $locale === 'ar'
            ? match ($alert->status) {
                'active' => 'نشط',
                'paused' => 'متوقف',
                'triggered' => 'تم تفعيله',
                default => $alert->status,
            }
            : ucfirst($alert->status);

        // Snoozed status
        $snoozedLine = '';
        if ($alert->snoozed_until) {
            $until = $alert->snoozed_until->format('H:i');
            $snoozedLine = $locale === 'ar'
                ? "\n😴 مؤجل حتى: {$until}"
                : "\n😴 Snoozed until: {$until}";
        }

        // Created at
        $created = $alert->created_at->diffForHumans();

        if ($locale === 'ar') {
            return <<<MSG
🏦 {$symbol} - {$name}
{$triggerIcon} النوع: {$triggerLabel}
{$paramDisplay}
{$directionIcon} الاتجاه: {$directionLabel}
📊 السعر الحالي: {$currentPrice} {$currency}

الحالة: {$statusIcon} {$statusLabel}{$snoozedLine}
تم الإنشاء: {$created}
MSG;
        }

        return <<<MSG
🏦 {$symbol} - {$name}
{$triggerIcon} Type: {$triggerLabel}
{$paramDisplay}
{$directionIcon} Direction: {$directionLabel}
📊 Current: {$currentPrice} {$currency}

Status: {$statusIcon} {$statusLabel}{$snoozedLine}
Created: {$created}
MSG;
    }

    /**
     * Get icon for trigger type.
     */
    private function getTriggerIcon(string $triggerType): string
    {
        return self::TRIGGER_TYPES[$triggerType]['icon'] ?? '📌';
    }

    /**
     * Get icon for direction.
     */
    private function getDirectionIcon(?string $direction): string
    {
        return self::DIRECTIONS[$direction]['icon'] ?? '';
    }

    /**
     * Get count of alerts triggered today.
     */
    private function getTriggeredTodayCount(User $user): int
    {
        return $user->alerts()
            ->whereDate('last_triggered_at', today())
            ->count();
    }
}
