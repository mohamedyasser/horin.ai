<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Models\Asset;
use App\Services\AlertCacheService;
use App\Services\AlertMatcher;
use App\Services\AlertScopeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        $pid = $this->signalData['pid'] ?? null;

        if (! $pid) {
            Log::warning('No pid in signal data', ['channel' => $this->channel]);

            return;
        }

        $assetId = $this->resolveAssetId($pid);

        if (! $assetId) {
            Log::warning('Could not resolve asset ID', ['pid' => $pid]);

            return;
        }

        // Quick check: does this asset have any alerts?
        if (! $cacheService->hasActiveAlerts($assetId)) {
            return;
        }

        // Determine alert type from channel
        $alertType = $this->getAlertTypeFromChannel($this->channel);

        // Get matching alerts
        $alerts = $this->getMatchingAlerts($cacheService, $alertType, $assetId, $scopeResolver);

        $triggeredCount = 0;

        foreach ($alerts as $alert) {
            if ($this->processAlert($alert, $matcher, $assetId)) {
                $triggeredCount++;
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        Log::debug('Processed intelligence alerts', [
            'channel' => $this->channel,
            'asset_id' => $assetId,
            'alerts_checked' => $alerts->count(),
            'triggered_count' => $triggeredCount,
            'duration_ms' => round($duration, 2),
        ]);
    }

    private function processAlert(Alert $alert, AlertMatcher $matcher, string $assetId): bool
    {
        if (! $alert->canTrigger()) {
            return false;
        }

        // Use appropriate evaluation method based on alert type
        if ($alert->type === 'price' && $this->channel === 'price_updates') {
            // Extract price from real-time update
            $currentPrice = $this->signalData['last'] ?? $this->signalData['price'] ?? null;
            $previousPrice = $this->signalData['prevClose'] ?? $this->signalData['prev_close'] ?? null;

            if (! $currentPrice) {
                return false;
            }

            $result = $matcher->evaluatePriceAlertFromSignal($alert, $currentPrice, $previousPrice, $this->signalData);
        } else {
            $result = $matcher->evaluateIntelligenceAlert($alert, $this->signalData);
        }

        if ($result->triggered) {
            $this->triggerAlert($alert, $result, $assetId);

            return true;
        }

        return false;
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
    ): Collection {
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
            // Signal classification priority channels
            str_starts_with($channel, 'classified_') => 'signal',
            // Signal classification action channels
            str_starts_with($channel, 'action_') => 'recommendation',
            // Pattern detection
            $channel === 'pattern_updates' => 'pattern',
            // Anomaly detection
            $channel === 'anomaly_alerts' => 'anomaly',
            // Recommendation service
            $channel === 'trading_recommendations' => 'recommendation',
            // Signal detection
            $channel === 'detected_signals' => 'signal',
            $channel === 'processed_signals' => 'signal',
            // Technical analysis
            $channel === 'technical_indicators' => 'signal',
            // Forecasting predictions
            $channel === 'price_predictions' => 'prediction',
            // Signal consumers execution
            $channel === 'execution_results' => 'signal',
            // Real-time price updates (for price alerts)
            $channel === 'price_updates' => 'price',
            default => 'signal',
        };
    }

    private function resolveAssetId(string $pid): ?string
    {
        // Cache this lookup for 1 hour
        return Cache::remember("asset_pid:{$pid}", 3600, function () use ($pid) {
            return Asset::where('inv_id', $pid)->value('id');
        });
    }
}
