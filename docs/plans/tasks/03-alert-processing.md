# Task 03: Alert Processing Engine

**Priority:** P0 (Critical Path)
**Effort:** 3 days
**Dependencies:** Task 02 (Core Models)

---

## Objective

Create jobs that process price updates and ML signals, match them against active alerts, and trigger notifications.

---

## Checklist

- [ ] Create ProcessPriceAlerts job
- [ ] Create ProcessIntelligenceAlerts job
- [ ] Create ProcessScheduledAlerts job
- [ ] Create ProcessCompoundAlerts job
- [ ] Create ProcessAlertChains job
- [ ] Create AlertMatcher service
- [ ] Configure Laravel scheduler
- [ ] Write tests for processing jobs

---

## Job 1: ProcessPriceAlerts

Processes price-based alerts (target_price, breakout, zone, daily_change, 52week, gap, entry_return).

```bash
php artisan make:job Alerts/ProcessPriceAlerts
```

```php
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

class ProcessPriceAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private ?string $assetId = null
    ) {}

    public function handle(
        AlertCacheService $cacheService,
        AlertMatcher $matcher
    ): void {
        // Skip if outside market hours
        if (!$this->isMarketOpen()) {
            return;
        }

        $startTime = microtime(true);

        // Get assets to process
        $assetIds = $this->assetId
            ? collect([$this->assetId])
            : $this->getAssetsWithActiveAlerts($cacheService);

        if ($assetIds->isEmpty()) {
            return;
        }

        // Batch fetch latest prices
        $prices = LatestAssetPrice::whereIn('asset_id', $assetIds)
            ->get()
            ->keyBy('asset_id');

        // Process each asset
        foreach ($assetIds as $assetId) {
            $price = $prices->get($assetId);
            if (!$price) continue;

            $alerts = $cacheService->getAlertsForAsset($assetId)
                ->filter(fn($alert) => $alert->type === 'price');

            foreach ($alerts as $alert) {
                $this->processAlert($alert, $price, $matcher);
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        Log::debug("Processed price alerts", [
            'assets_count' => $assetIds->count(),
            'duration_ms' => round($duration, 2),
        ]);
    }

    private function processAlert(Alert $alert, LatestAssetPrice $price, AlertMatcher $matcher): void
    {
        if (!$alert->canTrigger()) {
            return;
        }

        $result = $matcher->evaluatePriceAlert($alert, $price);

        if ($result->triggered) {
            $this->triggerAlert($alert, $result);
        }
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

        Log::info('Alert triggered', [
            'alert_id' => $alert->id,
            'trigger_type' => $alert->trigger_type,
            'trigger_value' => $result->triggerValue,
        ]);
    }

    private function getAssetsWithActiveAlerts(AlertCacheService $cacheService): \Illuminate\Support\Collection
    {
        return Alert::where('type', 'price')
            ->active()
            ->notSnoozed()
            ->notExpired()
            ->whereNotNull('asset_id')
            ->distinct()
            ->pluck('asset_id');
    }

    private function isMarketOpen(): bool
    {
        $cairo = new \DateTimeZone('Africa/Cairo');
        $now = new \DateTime('now', $cairo);

        $dayOfWeek = (int) $now->format('N');
        if ($dayOfWeek >= 6) return false; // Weekend (Fri-Sat for Egypt)

        $time = $now->format('H:i');
        return $time >= '10:00' && $time <= '14:30';
    }
}
```

---

## Job 2: ProcessIntelligenceAlerts

Processes ML-based alerts (signal, anomaly, pattern, recommendation).

```bash
php artisan make:job Alerts/ProcessIntelligenceAlerts
```

```php
<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Services\AlertCacheService;
use App\Services\AlertMatcher;
use App\Services\AlertScopeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIntelligenceAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $signalData,
        private string $channel
    ) {}

    public function handle(
        AlertCacheService $cacheService,
        AlertMatcher $matcher,
        AlertScopeResolver $scopeResolver
    ): void {
        $startTime = microtime(true);
        $assetId = $this->resolveAssetId($this->signalData['pid']);

        if (!$assetId) {
            Log::warning('Could not resolve asset ID', ['pid' => $this->signalData['pid']]);
            return;
        }

        // Quick check: does this asset have any alerts?
        if (!$cacheService->hasActiveAlerts($assetId)) {
            return;
        }

        // Determine alert type from channel
        $alertType = $this->getAlertTypeFromChannel($this->channel);

        // Get matching alerts
        $alerts = $this->getMatchingAlerts($cacheService, $alertType, $assetId, $scopeResolver);

        foreach ($alerts as $alert) {
            $this->processAlert($alert, $matcher, $assetId);
        }

        $duration = (microtime(true) - $startTime) * 1000;
        Log::debug("Processed intelligence alerts", [
            'channel' => $this->channel,
            'asset_id' => $assetId,
            'alerts_checked' => $alerts->count(),
            'duration_ms' => round($duration, 2),
        ]);
    }

    private function processAlert(Alert $alert, AlertMatcher $matcher, string $assetId): void
    {
        if (!$alert->canTrigger()) {
            return;
        }

        $result = $matcher->evaluateIntelligenceAlert($alert, $this->signalData);

        if ($result->triggered) {
            $this->triggerAlert($alert, $result, $assetId);
        }
    }

    private function triggerAlert(Alert $alert, object $result, string $assetId): void
    {
        $history = $alert->history()->create([
            'user_id' => $alert->user_id,
            'asset_id' => $assetId,
            'triggered_at' => now(),
            'trigger_value' => $result->triggerValue,
            'trigger_context' => array_merge($result->context, [
                'signal_data' => $this->signalData,
                'channel' => $this->channel,
            ]),
        ]);

        $alert->markAsTriggered();

        SendAlertNotification::dispatch($alert, $history, $result->context);
        ProcessAlertChains::dispatch($alert);

        Log::info('Intelligence alert triggered', [
            'alert_id' => $alert->id,
            'type' => $alert->type,
            'channel' => $this->channel,
        ]);
    }

    private function getMatchingAlerts(
        AlertCacheService $cacheService,
        string $alertType,
        string $assetId,
        AlertScopeResolver $scopeResolver
    ): \Illuminate\Support\Collection {
        // Get alerts by type
        $alerts = $cacheService->getAlertsByType($alertType);

        // Filter by scope
        return $alerts->filter(function ($alert) use ($assetId, $scopeResolver) {
            return $scopeResolver->matchesScope($alert, $assetId);
        });
    }

    private function getAlertTypeFromChannel(string $channel): string
    {
        return match (true) {
            str_starts_with($channel, 'classified_') => 'signal',
            str_starts_with($channel, 'action_') => 'recommendation',
            $channel === 'pattern_updates' => 'pattern',
            $channel === 'anomaly_alerts' => 'anomaly',
            $channel === 'trading_recommendations' => 'recommendation',
            default => 'signal',
        };
    }

    private function resolveAssetId(string $pid): ?string
    {
        // Cache this lookup
        return \Cache::remember("asset_pid:{$pid}", 3600, function () use ($pid) {
            return \App\Models\Asset::where('external_id', $pid)->value('id');
        });
    }
}
```

---

## Service: AlertMatcher

```bash
php artisan make:class Services/AlertMatcher
```

```php
<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\LatestAssetPrice;

class AlertMatcher
{
    public function __construct(
        private AlertScopeResolver $scopeResolver
    ) {}

    /**
     * Evaluate a price-based alert.
     */
    public function evaluatePriceAlert(Alert $alert, LatestAssetPrice $price): object
    {
        return match ($alert->trigger_type) {
            'target_price' => $this->evaluateTargetPrice($alert, $price),
            'breakout' => $this->evaluateBreakout($alert, $price),
            'zone' => $this->evaluateZone($alert, $price),
            'daily_change' => $this->evaluateDailyChange($alert, $price),
            '52week' => $this->evaluate52Week($alert, $price),
            'gap' => $this->evaluateGap($alert, $price),
            'entry_return' => $this->evaluateEntryReturn($alert, $price),
            default => $this->noTrigger(),
        };
    }

    /**
     * Evaluate an intelligence-based alert.
     */
    public function evaluateIntelligenceAlert(Alert $alert, array $signalData): object
    {
        return match ($alert->trigger_type) {
            'signal' => $this->evaluateSignal($alert, $signalData),
            'anomaly' => $this->evaluateAnomaly($alert, $signalData),
            'pattern' => $this->evaluatePattern($alert, $signalData),
            'recommendation' => $this->evaluateRecommendation($alert, $signalData),
            'prediction' => $this->evaluatePrediction($alert, $signalData),
            'compound_intelligence' => $this->evaluateCompound($alert, $signalData),
            default => $this->noTrigger(),
        };
    }

    // Price Alert Evaluators

    private function evaluateTargetPrice(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $target = $params['target_price'];
        $direction = $params['direction'] ?? $alert->direction ?? 'above';
        $currentPrice = $price->price;

        $triggered = match ($direction) {
            'above' => $currentPrice >= $target,
            'below' => $currentPrice <= $target,
            'both' => $currentPrice >= $target || $currentPrice <= $target,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'target' => $target,
                'direction' => $direction,
                'current_price' => $currentPrice,
                'change_percent' => $price->change_percent,
            ],
        ];
    }

    private function evaluateBreakout(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $level = $params['level'];
        $direction = $params['direction'] ?? 'above';
        $currentPrice = $price->price;

        $triggered = match ($direction) {
            'above' => $currentPrice > $level,
            'below' => $currentPrice < $level,
            default => false,
        };

        // Optional: Check confirmation (sustained above/below)
        if ($triggered && ($params['confirmation'] ?? null) === 'sustained') {
            // Would need historical tick data to confirm
            // For now, trust the current price
        }

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'level' => $level,
                'direction' => $direction,
                'current_price' => $currentPrice,
                'volume' => $price->volume,
            ],
        ];
    }

    private function evaluateZone(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $zoneLow = $params['zone_low'];
        $zoneHigh = $params['zone_high'];
        $triggerOn = $params['trigger_on'] ?? 'enter';
        $currentPrice = $price->price;

        $isInZone = $currentPrice >= $zoneLow && $currentPrice <= $zoneHigh;

        // Need to track previous state to detect enter/exit
        // For simplicity, trigger on "in zone" for enter
        $triggered = match ($triggerOn) {
            'enter' => $isInZone,
            'exit' => !$isInZone,
            'both' => true, // Would need state tracking
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'zone_low' => $zoneLow,
                'zone_high' => $zoneHigh,
                'is_in_zone' => $isInZone,
                'trigger_on' => $triggerOn,
            ],
        ];
    }

    private function evaluateDailyChange(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $threshold = $params['threshold_percent'];
        $direction = $params['direction'] ?? 'both';
        $changePercent = abs($price->change_percent);

        $triggered = $changePercent >= $threshold;

        if ($triggered && $direction !== 'both') {
            $triggered = match ($direction) {
                'up' => $price->change_percent > 0,
                'down' => $price->change_percent < 0,
                default => true,
            };
        }

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $price->change_percent,
            'context' => [
                'threshold' => $threshold,
                'direction' => $direction,
                'actual_change' => $price->change_percent,
                'current_price' => $price->price,
                'open_price' => $price->open,
            ],
        ];
    }

    private function evaluate52Week(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $type = $params['type'] ?? 'high';

        // Would need 52-week high/low data from asset or separate table
        $high52w = $price->high_52w ?? null;
        $low52w = $price->low_52w ?? null;
        $currentPrice = $price->price;

        $triggered = match ($type) {
            'high' => $high52w && $currentPrice >= $high52w,
            'low' => $low52w && $currentPrice <= $low52w,
            'both' => ($high52w && $currentPrice >= $high52w) || ($low52w && $currentPrice <= $low52w),
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'type' => $type,
                'high_52w' => $high52w,
                'low_52w' => $low52w,
                'current_price' => $currentPrice,
            ],
        ];
    }

    private function evaluateGap(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $threshold = $params['gap_threshold_percent'];
        $direction = $params['direction'] ?? 'both';

        $gapPercent = (($price->open - $price->prev_close) / $price->prev_close) * 100;
        $absGap = abs($gapPercent);

        $triggered = $absGap >= $threshold;

        if ($triggered && $direction !== 'both') {
            $triggered = match ($direction) {
                'up' => $gapPercent > 0,
                'down' => $gapPercent < 0,
                default => true,
            };
        }

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $gapPercent,
            'context' => [
                'threshold' => $threshold,
                'direction' => $direction,
                'gap_percent' => $gapPercent,
                'open' => $price->open,
                'prev_close' => $price->prev_close,
            ],
        ];
    }

    private function evaluateEntryReturn(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $entryPrice = $params['entry_price'] ?? $this->scopeResolver->getEntryPrice($alert, $price->asset_id);
        $tolerance = $params['tolerance_percent'] ?? 0.5;

        if (!$entryPrice) {
            return $this->noTrigger();
        }

        $currentPrice = $price->price;
        $diffPercent = abs(($currentPrice - $entryPrice) / $entryPrice) * 100;

        $triggered = $diffPercent <= $tolerance;

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'entry_price' => $entryPrice,
                'tolerance' => $tolerance,
                'current_price' => $currentPrice,
                'diff_percent' => $diffPercent,
            ],
        ];
    }

    // Intelligence Alert Evaluators

    private function evaluateSignal(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $requiredIndicators = $params['indicators'] ?? [];
        $requiredSignalTypes = $params['signal_types'] ?? [];
        $minStrength = $params['min_strength'] ?? 0.7;
        $anyOrAll = $params['any_or_all'] ?? 'any';

        $signal = $signalData['original_signal'] ?? $signalData;
        $indicator = $signal['indicator'] ?? '';
        $signalType = $signal['signal_type'] ?? '';
        $strength = $signal['strength'] ?? 0;

        // Check indicator match
        $indicatorMatch = empty($requiredIndicators) || in_array($indicator, $requiredIndicators);

        // Check signal type match
        $signalTypeMatch = empty($requiredSignalTypes) || in_array($signalType, $requiredSignalTypes);

        // Check strength
        $strengthMatch = $strength >= $minStrength;

        $triggered = $indicatorMatch && $signalTypeMatch && $strengthMatch;

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $strength,
            'context' => [
                'indicator' => $indicator,
                'signal_type' => $signalType,
                'strength' => $strength,
                'confidence' => $signal['confidence'] ?? null,
                'price' => $signal['price'] ?? null,
            ],
        ];
    }

    private function evaluateAnomaly(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $requiredTypes = $params['anomaly_types'] ?? [];
        $minConfidence = $params['min_confidence'] ?? 0.8;
        $severityLevels = $params['severity'] ?? ['high', 'critical'];

        $anomalyTypes = $signalData['types'] ?? [];
        $score = $signalData['score'] ?? 0;

        // Check type match
        $typeMatch = empty($requiredTypes) || count(array_intersect($requiredTypes, $anomalyTypes)) > 0;

        // Check confidence/score
        $scoreMatch = $score >= $minConfidence;

        // Determine severity
        $severity = match (true) {
            $score >= 0.9 => 'critical',
            $score >= 0.7 => 'high',
            $score >= 0.5 => 'medium',
            default => 'low',
        };
        $severityMatch = in_array($severity, $severityLevels);

        $triggered = $typeMatch && $scoreMatch && $severityMatch;

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $score,
            'context' => [
                'anomaly_types' => $anomalyTypes,
                'score' => $score,
                'severity' => $severity,
                'reasons' => $signalData['reasons'] ?? [],
                'price' => $signalData['price'] ?? null,
            ],
        ];
    }

    private function evaluatePattern(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $requiredPatterns = $params['patterns'] ?? [];
        $minConfidence = $params['min_confidence'] ?? 0.7;
        $patternStatus = $params['pattern_status'] ?? 'confirmed';

        $patterns = $signalData['patterns'] ?? [];

        foreach ($patterns as $pattern) {
            $typeMatch = empty($requiredPatterns) || in_array($pattern['type'], $requiredPatterns);
            $confidenceMatch = ($pattern['confidence'] ?? 0) >= $minConfidence;
            $statusMatch = $patternStatus === 'forming' || ($pattern['metadata']['breakout_confirmed'] ?? false);

            if ($typeMatch && $confidenceMatch && $statusMatch) {
                return (object) [
                    'triggered' => true,
                    'triggerValue' => $pattern['confidence'],
                    'context' => [
                        'pattern_type' => $pattern['type'],
                        'confidence' => $pattern['confidence'],
                        'support' => $pattern['support'] ?? null,
                        'resistance' => $pattern['resistance'] ?? null,
                        'target' => $pattern['target'] ?? null,
                    ],
                ];
            }
        }

        return $this->noTrigger();
    }

    private function evaluateRecommendation(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $triggerOn = $params['trigger_on'] ?? 'change';
        $targetRecs = $params['recommendations'] ?? ['strong_buy', 'buy'];
        $minScore = $params['min_score'] ?? 0.75;

        $action = strtolower($signalData['action'] ?? '');
        $score = $signalData['score'] ?? 0;
        $previousAction = strtolower($signalData['previous_action'] ?? '');

        $actionMatch = in_array($action, array_map('strtolower', $targetRecs));
        $scoreMatch = $score >= $minScore;
        $isChange = $action !== $previousAction;

        $triggered = match ($triggerOn) {
            'change' => $actionMatch && $scoreMatch && $isChange,
            'any' => $actionMatch && $scoreMatch,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $score,
            'context' => [
                'action' => $action,
                'previous_action' => $previousAction,
                'score' => $score,
                'is_upgrade' => $this->isUpgrade($previousAction, $action),
            ],
        ];
    }

    private function evaluatePrediction(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $direction = $params['direction'] ?? 'up';
        $minConfidence = $params['min_confidence'] ?? 0.75;

        $predictedDirection = $signalData['direction'] ?? '';
        $confidence = $signalData['confidence'] ?? 0;

        $directionMatch = $direction === 'both' || $predictedDirection === $direction;
        $confidenceMatch = $confidence >= $minConfidence;

        $triggered = $directionMatch && $confidenceMatch;

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $confidence,
            'context' => [
                'predicted_direction' => $predictedDirection,
                'confidence' => $confidence,
                'horizon' => $signalData['horizon'] ?? null,
                'expected_change' => $signalData['expected_change'] ?? null,
            ],
        ];
    }

    private function evaluateCompound(Alert $alert, array $signalData): object
    {
        // Compound alerts need all conditions from the parameters
        // This would be called from ProcessCompoundAlerts job
        return $this->noTrigger();
    }

    private function noTrigger(): object
    {
        return (object) [
            'triggered' => false,
            'triggerValue' => null,
            'context' => [],
        ];
    }

    private function isUpgrade(string $from, string $to): bool
    {
        $rankings = ['strong_sell' => 1, 'sell' => 2, 'hold' => 3, 'buy' => 4, 'strong_buy' => 5];
        return ($rankings[$to] ?? 0) > ($rankings[$from] ?? 0);
    }
}
```

---

## Job 3: ProcessAlertChains

```bash
php artisan make:job Alerts/ProcessAlertChains
```

```php
<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Models\AlertChain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAlertChains implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Alert $triggeredAlert
    ) {}

    public function handle(): void
    {
        // Find chains where this alert is the trigger
        $chains = AlertChain::where('trigger_alert_id', $this->triggeredAlert->id)
            ->where('is_active', true)
            ->get();

        foreach ($chains as $chain) {
            $this->activateChainedAlert($chain);
        }
    }

    private function activateChainedAlert(AlertChain $chain): void
    {
        $alertToActivate = Alert::find($chain->activate_alert_id);

        if (!$alertToActivate || $alertToActivate->status !== 'chained') {
            return;
        }

        // Apply delay if configured
        if ($chain->delay_minutes > 0) {
            ActivateChainedAlert::dispatch($alertToActivate, $chain)
                ->delay(now()->addMinutes($chain->delay_minutes));
        } else {
            $alertToActivate->update([
                'status' => 'active',
                'expires_at' => $chain->expires_after_minutes
                    ? now()->addMinutes($chain->expires_after_minutes)
                    : null,
            ]);
        }
    }
}
```

---

## Scheduler Configuration

```php
// routes/console.php

use App\Jobs\Alerts\ProcessPriceAlerts;
use App\Jobs\Alerts\ProcessScheduledAlerts;
use App\Jobs\Alerts\ProcessEscalation;
use App\Jobs\Alerts\GenerateDigest;
use Illuminate\Support\Facades\Schedule;

// Every minute: process price alerts
Schedule::job(new ProcessPriceAlerts())
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Market open (10:00 AM Cairo): gap alerts
Schedule::job(new ProcessScheduledAlerts('market_open'))
    ->dailyAt('10:00')
    ->timezone('Africa/Cairo')
    ->weekdays();

// Market close (14:30 PM Cairo): 52-week, daily change
Schedule::job(new ProcessScheduledAlerts('market_close'))
    ->dailyAt('14:30')
    ->timezone('Africa/Cairo')
    ->weekdays();

// Process escalations every 5 minutes
Schedule::job(new ProcessEscalation())
    ->everyFiveMinutes();

// Daily digest at 8 PM
Schedule::job(new GenerateDigest('daily'))
    ->dailyAt('20:00')
    ->timezone('Africa/Cairo');

// Weekly digest on Thursday (Egypt weekend starts Friday)
Schedule::job(new GenerateDigest('weekly'))
    ->weeklyOn(4, '15:00')
    ->timezone('Africa/Cairo');

// Refresh alert cache every minute
Schedule::call(fn() => app(\App\Services\AlertCacheService::class)->cacheActiveAlerts())
    ->everyMinute();
```

---

## Verification

```bash
# Test price alert processing
php artisan tinker
>>> ProcessPriceAlerts::dispatch();

# Test with specific asset
>>> ProcessPriceAlerts::dispatch('asset-uuid-here');

# Run scheduler
php artisan schedule:run
```

---

## Next Task

Proceed to [Task 04: Redis Channel Listener](./04-redis-listener.md)
