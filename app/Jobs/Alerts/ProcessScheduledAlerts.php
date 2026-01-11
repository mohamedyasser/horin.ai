<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Models\LatestAssetPrice;
use App\Services\AlertCacheService;
use App\Services\AlertMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  string  $eventType  The type of scheduled event: 'market_open', 'market_close'
     */
    public function __construct(
        private readonly string $eventType
    ) {}

    public function handle(
        AlertCacheService $cacheService,
        AlertMatcher $matcher
    ): void {
        $startTime = microtime(true);

        Log::info("Processing scheduled alerts for event: {$this->eventType}");

        // Get alerts that should trigger on this event
        $alerts = $this->getAlertsForEvent();

        if ($alerts->isEmpty()) {
            Log::debug("No alerts configured for event: {$this->eventType}");

            return;
        }

        $triggeredCount = 0;

        foreach ($alerts as $alert) {
            if (! $alert->canTrigger()) {
                continue;
            }

            $result = $this->evaluateAlert($alert, $matcher);

            if ($result->triggered) {
                $this->triggerAlert($alert, $result);
                $triggeredCount++;
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        Log::info("Processed scheduled alerts for {$this->eventType}", [
            'alerts_checked' => $alerts->count(),
            'triggered_count' => $triggeredCount,
            'duration_ms' => round($duration, 2),
        ]);
    }

    private function getAlertsForEvent()
    {
        $triggerTypes = $this->getTriggerTypesForEvent();

        return Alert::query()
            ->where('type', 'price')
            ->whereIn('trigger_type', $triggerTypes)
            ->active()
            ->notSnoozed()
            ->notExpired()
            ->with('asset')
            ->get();
    }

    private function getTriggerTypesForEvent(): array
    {
        return match ($this->eventType) {
            'market_open' => ['gap'],
            'market_close' => ['daily_change', '52week'],
            default => [],
        };
    }

    private function evaluateAlert(Alert $alert, AlertMatcher $matcher): object
    {
        $latestPrice = LatestAssetPrice::where('asset_id', $alert->asset_id)->first();

        if (! $latestPrice) {
            return (object) ['triggered' => false];
        }

        return match ($alert->trigger_type) {
            'gap' => $this->evaluateGapAlert($alert, $latestPrice, $matcher),
            'daily_change' => $this->evaluateDailyChangeAlert($alert, $latestPrice),
            '52week' => $this->evaluate52WeekAlert($alert, $latestPrice),
            default => (object) ['triggered' => false],
        };
    }

    private function evaluateGapAlert(Alert $alert, LatestAssetPrice $price, AlertMatcher $matcher): object
    {
        $params = $alert->parameters;
        $threshold = $params['gap_threshold_percent'] ?? 3;
        $direction = $params['direction'] ?? 'both';

        // Gap is the difference between today's open and yesterday's close
        if (! $price->open || ! $price->previous_close) {
            return (object) ['triggered' => false];
        }

        $gapPercent = (($price->open - $price->previous_close) / $price->previous_close) * 100;

        $triggered = match ($direction) {
            'above' => $gapPercent >= $threshold,
            'below' => $gapPercent <= -$threshold,
            'both' => abs($gapPercent) >= $threshold,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $price->open,
            'context' => [
                'event' => 'market_open',
                'gap_percent' => round($gapPercent, 2),
                'previous_close' => $price->previous_close,
                'open_price' => $price->open,
            ],
        ];
    }

    private function evaluateDailyChangeAlert(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $threshold = $params['threshold_percent'] ?? 5;
        $direction = $params['direction'] ?? 'both';
        $fromReference = $params['from_reference'] ?? 'open';

        $referencePrice = $fromReference === 'previous_close'
            ? ($price->previous_close ?? $price->open)
            : $price->open;

        if (! $referencePrice || $referencePrice <= 0) {
            return (object) ['triggered' => false];
        }

        $changePercent = (($price->close - $referencePrice) / $referencePrice) * 100;

        $triggered = match ($direction) {
            'above' => $changePercent >= $threshold,
            'below' => $changePercent <= -$threshold,
            'both' => abs($changePercent) >= $threshold,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $price->close,
            'context' => [
                'event' => 'market_close',
                'change_percent' => round($changePercent, 2),
                'reference_price' => $referencePrice,
                'close_price' => $price->close,
                'reference_type' => $fromReference,
            ],
        ];
    }

    private function evaluate52WeekAlert(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $type = $params['type'] ?? 'high'; // 'high' or 'low'

        // Check if current price is at 52-week high/low
        $is52WeekHigh = $price->close >= $price->week_52_high;
        $is52WeekLow = $price->close <= $price->week_52_low;

        $triggered = match ($type) {
            'high' => $is52WeekHigh,
            'low' => $is52WeekLow,
            'both' => $is52WeekHigh || $is52WeekLow,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $price->close,
            'context' => [
                'event' => 'market_close',
                'type' => $type,
                'is_52_week_high' => $is52WeekHigh,
                'is_52_week_low' => $is52WeekLow,
                'week_52_high' => $price->week_52_high,
                'week_52_low' => $price->week_52_low,
                'close_price' => $price->close,
            ],
        ];
    }

    private function triggerAlert(Alert $alert, object $result): void
    {
        // Create history record
        $history = $alert->history()->create([
            'user_id' => $alert->user_id,
            'asset_id' => $alert->asset_id,
            'triggered_at' => now(),
            'trigger_value' => $result->triggerValue,
            'trigger_context' => $result->context,
        ]);

        // Mark alert as triggered
        $alert->markAsTriggered();

        // Queue notification
        SendAlertNotification::dispatch($alert, $history, $result->context);

        // Process alert chains
        ProcessAlertChains::dispatch($alert);

        Log::info("Scheduled alert triggered", [
            'alert_id' => $alert->id,
            'event' => $this->eventType,
            'trigger_type' => $alert->trigger_type,
            'trigger_value' => $result->triggerValue,
        ]);
    }
}
