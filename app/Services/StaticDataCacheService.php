<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StaticDataCacheService
{
    private const TTL = 86400; // 24 hours

    private const PREFIX = 'static:';

    /**
     * Get all countries (cached).
     */
    public static function countries(): Collection
    {
        return Cache::remember(
            self::PREFIX.'countries',
            self::TTL,
            fn () => Country::query()->orderBy('name_en')->get()
        );
    }

    /**
     * Get all markets with country relation (cached).
     */
    public static function markets(): Collection
    {
        return Cache::remember(
            self::PREFIX.'markets',
            self::TTL,
            fn () => Market::query()->with('country')->orderBy('code')->get()
        );
    }

    /**
     * Get all sectors (cached).
     */
    public static function sectors(): Collection
    {
        return Cache::remember(
            self::PREFIX.'sectors',
            self::TTL,
            fn () => Sector::query()->orderBy('name_en')->get()
        );
    }

    /**
     * Get all assets with market and sector relations (cached).
     */
    public static function assets(): Collection
    {
        return Cache::remember(
            self::PREFIX.'assets:all',
            self::TTL,
            fn () => Asset::query()
                ->with(['market', 'sector'])
                ->orderBy('symbol')
                ->get()
        );
    }

    /**
     * Get assets for a specific market (cached).
     */
    public static function assetsByMarket(string $marketId): Collection
    {
        return Cache::remember(
            self::PREFIX.'assets:market:'.$marketId,
            self::TTL,
            fn () => Asset::query()
                ->where('market_id', $marketId)
                ->with(['market', 'sector'])
                ->orderBy('symbol')
                ->get()
        );
    }

    /**
     * Get assets for a specific sector (cached).
     */
    public static function assetsBySector(string $sectorId): Collection
    {
        return Cache::remember(
            self::PREFIX.'assets:sector:'.$sectorId,
            self::TTL,
            fn () => Asset::query()
                ->where('sector_id', $sectorId)
                ->with(['market', 'sector'])
                ->orderBy('symbol')
                ->get()
        );
    }

    /**
     * Get a single market by code (cached).
     */
    public static function marketByCode(string $code): ?Market
    {
        return self::markets()->firstWhere('code', $code);
    }

    /**
     * Get a single sector by ID (cached).
     */
    public static function sectorById(string $id): ?Sector
    {
        return self::sectors()->firstWhere('id', $id);
    }

    /**
     * Clear all static data caches.
     */
    public static function clearAll(): void
    {
        $keys = [
            self::PREFIX.'countries',
            self::PREFIX.'markets',
            self::PREFIX.'sectors',
            self::PREFIX.'assets:all',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Clear market-specific asset caches
        $markets = Market::query()->pluck('id');
        foreach ($markets as $marketId) {
            Cache::forget(self::PREFIX.'assets:market:'.$marketId);
        }

        // Clear sector-specific asset caches
        $sectors = Sector::query()->pluck('id');
        foreach ($sectors as $sectorId) {
            Cache::forget(self::PREFIX.'assets:sector:'.$sectorId);
        }
    }

    /**
     * Warm all static data caches.
     */
    public static function warmAll(): void
    {
        self::countries();
        self::markets();
        self::sectors();
        self::assets();

        // Warm market-specific caches
        foreach (Market::query()->pluck('id') as $marketId) {
            self::assetsByMarket($marketId);
        }

        // Warm sector-specific caches
        foreach (Sector::query()->pluck('id') as $sectorId) {
            self::assetsBySector($sectorId);
        }
    }
}
