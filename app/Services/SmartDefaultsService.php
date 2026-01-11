<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;

class SmartDefaultsService
{
    /**
     * Suggest alert parameters based on user profile and asset
     */
    public function suggestParameters(User $user, Asset $asset, string $triggerType): array
    {
        return match ($triggerType) {
            'target_price' => $this->suggestTargetPrice($user, $asset),
            'daily_change' => $this->suggestDailyChange($asset),
            'zone' => $this->suggestZone($asset),
            'signal' => $this->suggestSignalParameters($user, $asset),
            default => [],
        };
    }

    private function suggestTargetPrice(User $user, Asset $asset): array
    {
        $currentPrice = $asset->last_price;
        $holding = $user->portfolioHoldings()
            ->where('asset_id', $asset->id)
            ->first();

        // If user owns the stock, suggest based on entry price
        if ($holding) {
            $entryPrice = $holding->average_cost;
            $profitTarget = $entryPrice * 1.10; // 10% profit
            $stopLoss = $entryPrice * 0.95; // 5% stop loss

            return [
                'suggested_targets' => [
                    [
                        'label' => '10% Profit Target',
                        'label_ar' => 'هدف ربح 10%',
                        'value' => round($profitTarget, 2),
                        'direction' => 'above',
                    ],
                    [
                        'label' => '5% Stop Loss',
                        'label_ar' => 'وقف خسارة 5%',
                        'value' => round($stopLoss, 2),
                        'direction' => 'below',
                    ],
                ],
                'entry_price' => $entryPrice,
            ];
        }

        // For watchlist stocks, suggest based on technical levels
        $recentHigh = $this->getRecentHigh($asset, 20);
        $recentLow = $this->getRecentLow($asset, 20);

        return [
            'suggested_targets' => [
                [
                    'label' => '20-Day High Breakout',
                    'label_ar' => 'اختراق أعلى 20 يوم',
                    'value' => round($recentHigh, 2),
                    'direction' => 'above',
                ],
                [
                    'label' => '20-Day Low Breakdown',
                    'label_ar' => 'كسر أدنى 20 يوم',
                    'value' => round($recentLow, 2),
                    'direction' => 'below',
                ],
            ],
        ];
    }

    private function suggestDailyChange(Asset $asset): array
    {
        // Calculate average daily volatility
        $avgVolatility = $this->calculateAverageVolatility($asset, 20);

        // Suggest threshold based on volatility
        $suggestedThreshold = max(round($avgVolatility * 1.5, 1), 2.0);

        return [
            'average_volatility' => round($avgVolatility, 2),
            'suggested_threshold' => $suggestedThreshold,
            'recommendation' => $avgVolatility > 3
                ? 'This stock is highly volatile. Consider a higher threshold.'
                : 'This stock has moderate volatility.',
            'recommendation_ar' => $avgVolatility > 3
                ? 'هذا السهم شديد التذبذب. فكر في عتبة أعلى.'
                : 'هذا السهم ذو تذبذب معتدل.',
        ];
    }

    private function suggestZone(Asset $asset): array
    {
        // Get support/resistance from technical data
        $technicals = $this->getTechnicalLevels($asset);

        return [
            'suggested_zones' => [
                [
                    'label' => 'Nearest Support Zone',
                    'label_ar' => 'أقرب منطقة دعم',
                    'zone_low' => round($technicals['support_1'] * 0.99, 2),
                    'zone_high' => round($technicals['support_1'] * 1.01, 2),
                ],
                [
                    'label' => 'Nearest Resistance Zone',
                    'label_ar' => 'أقرب منطقة مقاومة',
                    'zone_low' => round($technicals['resistance_1'] * 0.99, 2),
                    'zone_high' => round($technicals['resistance_1'] * 1.01, 2),
                ],
            ],
            'pivot_point' => $technicals['pivot'],
        ];
    }

    private function suggestSignalParameters(User $user, Asset $asset): array
    {
        // Get user's successful alert history
        $successfulSignals = Alert::where('user_id', $user->id)
            ->where('type', 'signal')
            ->where('triggered_count', '>', 0)
            ->get();

        // Find most commonly used indicators
        $indicatorCounts = [];
        foreach ($successfulSignals as $alert) {
            $indicators = $alert->parameters['indicators'] ?? [];
            foreach ($indicators as $indicator) {
                $indicatorCounts[$indicator] = ($indicatorCounts[$indicator] ?? 0) + 1;
            }
        }

        arsort($indicatorCounts);
        $topIndicators = array_slice(array_keys($indicatorCounts), 0, 3);

        if (empty($topIndicators)) {
            $topIndicators = ['RSI', 'MACD'];
        }

        return [
            'suggested_indicators' => $topIndicators,
            'recommended_min_strength' => 0.7,
            'note' => 'Based on your alert history',
            'note_ar' => 'بناءً على سجل تنبيهاتك',
        ];
    }

    private function getRecentHigh(Asset $asset, int $days): float
    {
        return $asset->prices()
            ->where('date', '>=', now()->subDays($days))
            ->max('high') ?? $asset->last_price;
    }

    private function getRecentLow(Asset $asset, int $days): float
    {
        return $asset->prices()
            ->where('date', '>=', now()->subDays($days))
            ->min('low') ?? $asset->last_price;
    }

    private function calculateAverageVolatility(Asset $asset, int $days): float
    {
        $prices = $asset->prices()
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date')
            ->get();

        if ($prices->count() < 2) {
            return 2.0; // Default
        }

        $dailyChanges = [];
        for ($i = 1; $i < $prices->count(); $i++) {
            $change = abs(($prices[$i]->close - $prices[$i - 1]->close) / $prices[$i - 1]->close * 100);
            $dailyChanges[] = $change;
        }

        return array_sum($dailyChanges) / count($dailyChanges);
    }

    private function getTechnicalLevels(Asset $asset): array
    {
        // Simplified pivot point calculation
        $latestPrice = $asset->prices()->latest('date')->first();

        if (! $latestPrice) {
            return [
                'pivot' => $asset->last_price,
                'support_1' => $asset->last_price * 0.98,
                'resistance_1' => $asset->last_price * 1.02,
            ];
        }

        $pivot = ($latestPrice->high + $latestPrice->low + $latestPrice->close) / 3;
        $support1 = 2 * $pivot - $latestPrice->high;
        $resistance1 = 2 * $pivot - $latestPrice->low;

        return [
            'pivot' => round($pivot, 2),
            'support_1' => round($support1, 2),
            'resistance_1' => round($resistance1, 2),
        ];
    }
}
