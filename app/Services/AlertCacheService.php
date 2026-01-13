<?php

namespace App\Services;

use App\Models\Alert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AlertCacheService
{
    private const CACHE_TTL = 60; // seconds

    /**
     * Cache active alerts by asset for fast lookup.
     */
    public function cacheActiveAlerts(): void
    {
        $alerts = Alert::active()
            ->notSnoozed()
            ->notExpired()
            ->get();

        // Group by asset_id
        $byAsset = $alerts->groupBy('asset_id');
        foreach ($byAsset as $assetId => $assetAlerts) {
            if ($assetId) {
                Cache::put(
                    "active_alerts:asset:{$assetId}",
                    $assetAlerts->toArray(),
                    self::CACHE_TTL
                );
            }
        }

        // Group by type for intelligence alerts
        $byType = $alerts->groupBy('type');
        foreach ($byType as $type => $typeAlerts) {
            Cache::put(
                "active_alerts:type:{$type}",
                $typeAlerts->toArray(),
                self::CACHE_TTL
            );
        }

        // Store asset IDs with active alerts in a Redis set
        $assetIds = $byAsset->keys()->filter()->toArray();
        if (count($assetIds) > 0) {
            Redis::del('active_alert_assets');
            Redis::sadd('active_alert_assets', ...$assetIds);
        }
    }

    /**
     * Get alerts for a specific asset.
     */
    public function getAlertsForAsset(string $assetId): Collection
    {
        $cached = Cache::get("active_alerts:asset:{$assetId}");

        if ($cached) {
            return collect($cached)->map(fn ($data) => $this->hydrateAlert($data));
        }

        $alerts = Alert::active()
            ->notSnoozed()
            ->notExpired()
            ->forAsset($assetId)
            ->get();

        Cache::put("active_alerts:asset:{$assetId}", $alerts->toArray(), self::CACHE_TTL);

        return $alerts;
    }

    /**
     * Get alerts by type.
     */
    public function getAlertsByType(string $type): Collection
    {
        $cached = Cache::get("active_alerts:type:{$type}");

        if ($cached) {
            return collect($cached)->map(fn ($data) => $this->hydrateAlert($data));
        }

        $alerts = Alert::active()
            ->notSnoozed()
            ->notExpired()
            ->ofType($type)
            ->get();

        Cache::put("active_alerts:type:{$type}", $alerts->toArray(), self::CACHE_TTL);

        return $alerts;
    }

    /**
     * Check if asset has any active alerts (O(1) lookup).
     */
    public function hasActiveAlerts(string $assetId): bool
    {
        return (bool) Redis::sismember('active_alert_assets', $assetId);
    }

    /**
     * Get all asset IDs with active alerts.
     */
    public function getAssetsWithActiveAlerts(): array
    {
        return Redis::smembers('active_alert_assets') ?: [];
    }

    /**
     * Invalidate cache for an asset.
     */
    public function invalidateAsset(?string $assetId): void
    {
        if ($assetId) {
            Cache::forget("active_alerts:asset:{$assetId}");
        }
        $this->refreshAssetSet();
    }

    /**
     * Invalidate cache for a type.
     */
    public function invalidateType(string $type): void
    {
        Cache::forget("active_alerts:type:{$type}");
    }

    /**
     * Invalidate all alert caches.
     */
    public function invalidateAll(): void
    {
        // Get all cached asset IDs and clear them
        $assetIds = $this->getAssetsWithActiveAlerts();
        foreach ($assetIds as $assetId) {
            Cache::forget("active_alerts:asset:{$assetId}");
        }

        // Clear type caches
        $types = ['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation'];
        foreach ($types as $type) {
            Cache::forget("active_alerts:type:{$type}");
        }

        // Clear the asset set
        Redis::del('active_alert_assets');
    }

    /**
     * Refresh the Redis set of assets with active alerts.
     */
    private function refreshAssetSet(): void
    {
        $assetIds = Alert::active()
            ->notSnoozed()
            ->notExpired()
            ->whereNotNull('asset_id')
            ->distinct()
            ->pluck('asset_id')
            ->toArray();

        Redis::del('active_alert_assets');
        if (count($assetIds) > 0) {
            Redis::sadd('active_alert_assets', ...$assetIds);
        }
    }

    /**
     * Get cache statistics for monitoring.
     */
    public function getStats(): array
    {
        $assetIds = $this->getAssetsWithActiveAlerts();

        return [
            'assets_with_alerts' => count($assetIds),
            'cache_ttl_seconds' => self::CACHE_TTL,
        ];
    }

    /**
     * Hydrate an Alert model from cached array data.
     *
     * Uses forceFill to bypass mass assignment protection (needed for 'id')
     * and sets exists=true so Laravel treats it as a persisted record.
     */
    private function hydrateAlert(array $data): Alert
    {
        $alert = (new Alert)->forceFill($data);
        $alert->exists = true;

        return $alert;
    }
}
