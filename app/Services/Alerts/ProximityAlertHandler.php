<?php

namespace App\Services\Alerts;

use App\Jobs\Alerts\SendAlertNotification;
use App\Models\Alert;
use Illuminate\Support\Facades\Cache;

class ProximityAlertHandler
{
    /**
     * Check if price is approaching a target alert
     */
    public function checkProximity(Alert $targetAlert, float $currentPrice): void
    {
        $targetPrice = $targetAlert->parameters['target_price'] ?? null;

        if (! $targetPrice) {
            return;
        }

        $proximityPercent = $targetAlert->parameters['proximity_percent'] ?? 2.0;
        $proximityRange = $targetPrice * ($proximityPercent / 100);

        $lowerBound = $targetPrice - $proximityRange;
        $upperBound = $targetPrice + $proximityRange;

        // Check if price entered proximity zone
        $inProximity = $currentPrice >= $lowerBound && $currentPrice <= $upperBound;

        if (! $inProximity) {
            return;
        }

        // Check if we already notified for this approach
        $cacheKey = "proximity_alert:{$targetAlert->id}";
        if (Cache::has($cacheKey)) {
            return;
        }

        // Send proximity notification
        $this->sendProximityNotification($targetAlert, $currentPrice, $targetPrice);

        // Cache to prevent repeated notifications
        // Reset cache if price moves away significantly (5%+)
        Cache::put($cacheKey, true, now()->addHours(4));
    }

    /**
     * Check if price has moved away from target, clearing proximity cache
     */
    public function checkProximityReset(Alert $targetAlert, float $currentPrice): void
    {
        $targetPrice = $targetAlert->parameters['target_price'] ?? null;

        if (! $targetPrice) {
            return;
        }

        $resetThreshold = 5.0; // 5% away from target
        $distance = abs($currentPrice - $targetPrice);
        $distancePercent = ($distance / $targetPrice) * 100;

        if ($distancePercent >= $resetThreshold) {
            $cacheKey = "proximity_alert:{$targetAlert->id}";
            Cache::forget($cacheKey);
        }
    }

    private function sendProximityNotification(Alert $alert, float $currentPrice, float $targetPrice): void
    {
        $distance = abs($currentPrice - $targetPrice);
        $distancePercent = ($distance / $targetPrice) * 100;

        // Create a special proximity history entry
        $history = $alert->history()->create([
            'user_id' => $alert->user_id,
            'asset_id' => $alert->asset_id,
            'triggered_at' => now(),
            'trigger_value' => $currentPrice,
            'trigger_context' => [
                'type' => 'proximity',
                'target_price' => $targetPrice,
                'distance_percent' => round($distancePercent, 2),
            ],
        ]);

        // Dispatch notification with proximity flag
        SendAlertNotification::dispatch($alert, $history, [
            'is_proximity' => true,
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
            'distance_percent' => round($distancePercent, 2),
        ]);
    }
}
