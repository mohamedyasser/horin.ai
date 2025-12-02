<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PredictionStatsService
{
    private const CACHE_TTL = 60;

    public static function countsByMarket(): Collection
    {
        return Cache::remember('prediction_counts_by_market', self::CACHE_TTL, function () {
            return DB::table('predicted_asset_prices as p')
                ->join('assets as a', 'p.pid', '=', 'a.inv_id')
                ->select('a.market_id', DB::raw('COUNT(*) as prediction_count'))
                ->groupBy('a.market_id')
                ->pluck('prediction_count', 'market_id');
        });
    }

    public static function countsBySector(): Collection
    {
        return Cache::remember('prediction_counts_by_sector', self::CACHE_TTL, function () {
            return DB::table('predicted_asset_prices as p')
                ->join('assets as a', 'p.pid', '=', 'a.inv_id')
                ->select('a.sector_id', DB::raw('COUNT(*) as prediction_count'))
                ->groupBy('a.sector_id')
                ->pluck('prediction_count', 'sector_id');
        });
    }

    public static function countForMarket(string $marketId): int
    {
        return self::countsByMarket()->get($marketId, 0);
    }

    public static function countForSector(string $sectorId): int
    {
        return self::countsBySector()->get($sectorId, 0);
    }

    public static function clearCache(): void
    {
        Cache::forget('prediction_counts_by_market');
        Cache::forget('prediction_counts_by_sector');
    }
}
