<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Models\AlertBacktestResult;
use App\Models\AssetPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RunAlertBacktest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300; // 5 minutes max

    public function __construct(
        private readonly Alert $alert,
        private readonly int $lookbackDays = 90,
        private readonly bool $includeMlSignals = false
    ) {}

    public function handle(): void
    {
        $startTime = microtime(true);
        $startDate = now()->subDays($this->lookbackDays);

        // Load historical price data
        $prices = AssetPrice::where('asset_id', $this->alert->asset_id)
            ->where('date', '>=', $startDate)
            ->orderBy('date')
            ->get();

        if ($prices->isEmpty()) {
            $this->createEmptyResult();

            return;
        }

        $triggers = [];
        $previousPrice = null;

        // Simulate alert matching day by day
        foreach ($prices as $index => $price) {
            $wouldTrigger = $this->evaluateHistoricalTrigger($price, $previousPrice);

            if ($wouldTrigger) {
                $triggers[] = [
                    'date' => $price->date->toDateString(),
                    'trigger_price' => (float) $price->close,
                    'performance' => $this->calculatePerformance($prices, $index),
                ];
            }

            $previousPrice = $price;
        }

        // Calculate summary statistics
        $returns1d = collect($triggers)->pluck('performance.1d')->filter()->values();
        $returns1w = collect($triggers)->pluck('performance.1w')->filter()->values();
        $returns1m = collect($triggers)->pluck('performance.1m')->filter()->values();

        AlertBacktestResult::create([
            'alert_id' => $this->alert->id,
            'lookback_days' => $this->lookbackDays,
            'trigger_count' => count($triggers),
            'triggers' => $triggers,
            'avg_return_1d' => $returns1d->isNotEmpty() ? $returns1d->avg() : null,
            'avg_return_1w' => $returns1w->isNotEmpty() ? $returns1w->avg() : null,
            'avg_return_1m' => $returns1m->isNotEmpty() ? $returns1m->avg() : null,
            'win_rate' => $this->calculateWinRate($triggers),
            'completed_at' => now(),
        ]);

        $duration = (microtime(true) - $startTime) * 1000;
        Log::info('Backtest completed', [
            'alert_id' => $this->alert->id,
            'lookback_days' => $this->lookbackDays,
            'trigger_count' => count($triggers),
            'duration_ms' => round($duration, 2),
        ]);
    }

    private function evaluateHistoricalTrigger(AssetPrice $currentPrice, ?AssetPrice $previousPrice): bool
    {
        $params = $this->alert->parameters;
        $triggerType = $this->alert->trigger_type;

        return match ($triggerType) {
            'target_price' => $this->evaluateTargetPrice($currentPrice, $previousPrice, $params),
            'breakout' => $this->evaluateBreakout($currentPrice, $previousPrice, $params),
            'zone' => $this->evaluateZone($currentPrice, $params),
            'daily_change' => $this->evaluateDailyChange($currentPrice, $params),
            '52week' => $this->evaluate52Week($currentPrice, $params),
            default => false,
        };
    }

    private function evaluateTargetPrice(AssetPrice $current, ?AssetPrice $previous, array $params): bool
    {
        $target = $params['target_price'] ?? 0;
        $direction = $params['direction'] ?? 'above';

        if (! $previous) {
            return false;
        }

        if ($direction === 'above') {
            return $previous->close < $target && $current->close >= $target;
        }

        if ($direction === 'below') {
            return $previous->close > $target && $current->close <= $target;
        }

        // 'both' direction
        return ($previous->close < $target && $current->close >= $target)
            || ($previous->close > $target && $current->close <= $target);
    }

    private function evaluateBreakout(AssetPrice $current, ?AssetPrice $previous, array $params): bool
    {
        $level = $params['level'] ?? 0;
        $direction = $params['direction'] ?? 'above';

        if (! $previous) {
            return false;
        }

        if ($direction === 'above') {
            return $previous->close < $level && $current->close > $level;
        }

        return $previous->close > $level && $current->close < $level;
    }

    private function evaluateZone(AssetPrice $current, array $params): bool
    {
        $low = $params['zone_low'] ?? 0;
        $high = $params['zone_high'] ?? 0;
        $triggerOn = $params['trigger_on'] ?? 'enter';

        $inZone = $current->close >= $low && $current->close <= $high;

        return $triggerOn === 'enter' ? $inZone : ! $inZone;
    }

    private function evaluateDailyChange(AssetPrice $current, array $params): bool
    {
        $threshold = $params['threshold_percent'] ?? 5;
        $direction = $params['direction'] ?? 'both';

        if ($current->open <= 0) {
            return false;
        }

        $change = (($current->close - $current->open) / $current->open) * 100;

        if ($direction === 'above') {
            return $change >= $threshold;
        }

        if ($direction === 'below') {
            return $change <= -$threshold;
        }

        return abs($change) >= $threshold;
    }

    private function evaluate52Week(AssetPrice $current, array $params): bool
    {
        $type = $params['type'] ?? 'high';

        // Simplified check - in real implementation would query 52-week range
        return false;
    }

    private function calculatePerformance(Collection $prices, int $triggerIndex): array
    {
        $triggerPrice = (float) $prices[$triggerIndex]->close;

        return [
            '1d' => $this->getReturn($prices, $triggerIndex, 1, $triggerPrice),
            '1w' => $this->getReturn($prices, $triggerIndex, 5, $triggerPrice),
            '1m' => $this->getReturn($prices, $triggerIndex, 22, $triggerPrice),
        ];
    }

    private function getReturn(Collection $prices, int $triggerIndex, int $daysAhead, float $triggerPrice): ?float
    {
        $futureIndex = $triggerIndex + $daysAhead;

        if ($futureIndex >= $prices->count()) {
            return null;
        }

        $futurePrice = (float) $prices[$futureIndex]->close;

        if ($triggerPrice <= 0) {
            return null;
        }

        return ($futurePrice - $triggerPrice) / $triggerPrice;
    }

    private function calculateWinRate(array $triggers): ?float
    {
        if (empty($triggers)) {
            return null;
        }

        $wins = 0;
        $total = 0;

        foreach ($triggers as $trigger) {
            $return1d = $trigger['performance']['1d'] ?? null;
            if ($return1d !== null) {
                $total++;
                if ($return1d > 0) {
                    $wins++;
                }
            }
        }

        return $total > 0 ? $wins / $total : null;
    }

    private function createEmptyResult(): void
    {
        AlertBacktestResult::create([
            'alert_id' => $this->alert->id,
            'lookback_days' => $this->lookbackDays,
            'trigger_count' => 0,
            'triggers' => [],
            'completed_at' => now(),
        ]);
    }
}
