<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Models\LatestAssetPrice;
use App\Services\AlertCacheService;
use App\Services\AlertMatcher;
use DateTime;
use DateTimeZone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
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
        // Skip if outside market hours (unless processing specific asset)
        if (! $this->assetId && ! $this->isMarketOpen()) {
            return;
        }

        $startTime = microtime(true);

        // Get assets to process
        $assetIds = $this->assetId
            ? collect([$this->assetId])
            : $this->getAssetsWithActiveAlerts();

        if ($assetIds->isEmpty()) {
            return;
        }

        // Batch fetch latest prices
        $prices = LatestAssetPrice::whereIn('asset_id', $assetIds)
            ->get()
            ->keyBy('asset_id');

        $triggeredCount = 0;

        // Process each asset
        foreach ($assetIds as $assetId) {
            $price = $prices->get($assetId);
            if (! $price) {
                continue;
            }

            $alerts = $cacheService->getAlertsForAsset($assetId)
                ->filter(fn ($alert) => $alert->type === 'price');

            foreach ($alerts as $alert) {
                if ($this->processAlert($alert, $price, $matcher)) {
                    $triggeredCount++;
                }
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        Log::debug('Processed price alerts', [
            'assets_count' => $assetIds->count(),
            'triggered_count' => $triggeredCount,
            'duration_ms' => round($duration, 2),
        ]);
    }

    private function processAlert(Alert $alert, LatestAssetPrice $price, AlertMatcher $matcher): bool
    {
        if (! $alert->canTrigger()) {
            return false;
        }

        $result = $matcher->evaluatePriceAlert($alert, $price);

        if ($result->triggered) {
            $this->triggerAlert($alert, $result);

            return true;
        }

        return false;
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

    private function getAssetsWithActiveAlerts(): Collection
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
        $cairo = new DateTimeZone('Africa/Cairo');
        $now = new DateTime('now', $cairo);

        $dayOfWeek = (int) $now->format('N');
        // Egypt weekend is Friday (5) and Saturday (6)
        if ($dayOfWeek >= 5) {
            return false;
        }

        $time = $now->format('H:i');

        return $time >= '10:00' && $time <= '14:30';
    }
}
