<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\LatestAssetPrice;
use App\Models\LatestPrediction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SearchService
{
    private const DEFAULT_PER_PAGE = 10;

    /**
     * Search assets globally (for Search page).
     */
    public static function searchAssets(
        string $query,
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1
    ): LengthAwarePaginator {
        return Asset::search($query)
            ->query(fn ($q) => $q->with(['market', 'sector']))
            ->paginate($perPage, 'page', $page);
    }

    /**
     * Search assets within a specific market.
     */
    public static function searchAssetsInMarket(
        string $marketId,
        ?string $query = null,
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1
    ): LengthAwarePaginator {
        $builder = Asset::search($query ?? '')
            ->where('market_id', $marketId)
            ->query(fn ($q) => $q->with(['market', 'sector', 'cachedPrice', 'cachedPrediction']));

        return $builder->paginate($perPage, 'page', $page);
    }

    /**
     * Search assets within a specific sector.
     */
    public static function searchAssetsInSector(
        string $sectorId,
        ?string $query = null,
        int $perPage = self::DEFAULT_PER_PAGE,
        int $page = 1
    ): LengthAwarePaginator {
        $builder = Asset::search($query ?? '')
            ->where('sector_id', $sectorId)
            ->query(fn ($q) => $q->with(['market', 'sector', 'cachedPrice', 'cachedPrediction']));

        return $builder->paginate($perPage, 'page', $page);
    }

    /**
     * Search assets with their predictions (for homepage).
     */
    public static function searchAssetsWithPredictions(
        string $query,
        ?string $marketId = null,
        int $limit = 20
    ): Collection {
        $builder = Asset::search($query)
            ->query(fn ($q) => $q->with(['market', 'sector']));

        if ($marketId) {
            $builder->where('market_id', $marketId);
        }

        $assets = $builder->take($limit)->get();

        // Fetch prices and predictions
        $assetIds = $assets->pluck('id')->toArray();
        $invIds = $assets->pluck('inv_id')->filter()->toArray();

        $prices = LatestAssetPrice::whereIn('pid', $invIds)->get()->keyBy('pid');
        $predictions = LatestPrediction::whereIn('asset_id', $assetIds)->get()->keyBy('asset_id');

        return $assets->map(function ($asset) use ($prices, $predictions) {
            $price = $prices[$asset->inv_id] ?? null;
            $prediction = $predictions[$asset->id] ?? null;

            return [
                'asset' => $asset,
                'price' => $price,
                'prediction' => $prediction,
            ];
        });
    }

    /**
     * Format asset for API response.
     *
     * @return array<string, mixed>
     */
    public static function formatAsset(Asset $asset, ?LatestAssetPrice $price = null): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $locale === 'ar' ? $asset->name_ar : $asset->name_en,
            'market' => $asset->market ? [
                'id' => $asset->market->id,
                'code' => $asset->market->code,
                'name' => $locale === 'ar' ? $asset->market->name_ar : $asset->market->name_en,
            ] : null,
            'sector' => $asset->sector ? [
                'id' => $asset->sector->id,
                'name' => $locale === 'ar' ? $asset->sector->name_ar : $asset->sector->name_en,
            ] : null,
            'latestPrice' => $price ? [
                'last' => $price->price,
                'pcp' => $price->percent_change,
                'freshness' => $price->freshness,
                'hoursAgo' => $price->hours_ago,
            ] : null,
        ];
    }

    /**
     * Format asset with prediction for API response.
     *
     * @return array<string, mixed>
     */
    public static function formatAssetWithPrediction(
        Asset $asset,
        ?LatestAssetPrice $price = null,
        ?LatestPrediction $prediction = null
    ): array {
        $formatted = self::formatAsset($asset, $price);

        $formatted['latestPrediction'] = $prediction ? [
            'predictedPrice' => $prediction->price_prediction,
            'confidence' => $prediction->confidence,
            'horizon' => $prediction->horizon,
            'horizonLabel' => $prediction->horizon_label,
            'freshness' => $prediction->freshness,
        ] : null;

        return $formatted;
    }
}
