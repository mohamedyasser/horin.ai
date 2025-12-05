<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Market;
use App\Models\Sector;
use App\Services\PredictionStatsService;
use App\Services\SearchService;
use App\Services\StaticDataCacheService;
use App\Support\PaginationHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectorController extends Controller
{
    public function index(): Response
    {
        $predictionCounts = PredictionStatsService::countsBySector();

        // Use cached data for markets breakdown calculation
        $assets = StaticDataCacheService::assets();
        $markets = StaticDataCacheService::markets();

        $marketsBreakdown = $assets
            ->whereNotNull('sector_id')
            ->groupBy('sector_id')
            ->map(fn ($sectorAssets) => $sectorAssets
                ->groupBy('market_id')
                ->map(function ($marketAssets, $marketId) use ($markets) {
                    $market = $markets->firstWhere('id', $marketId);

                    return [
                        'marketId' => $marketId,
                        'marketCode' => $market?->code,
                        'marketName' => $market?->name,
                        'count' => $marketAssets->count(),
                    ];
                })
                ->values()
            );

        // Use cached sectors
        $sectors = StaticDataCacheService::sectors()
            ->map(fn ($sector) => [
                'id' => $sector->id,
                'name' => $sector->name,
                'description' => app()->getLocale() === 'ar'
                    ? $sector->description_ar
                    : $sector->description_en,
                'assetCount' => StaticDataCacheService::assetsBySector($sector->id)->count(),
                'predictionCount' => $predictionCounts->get($sector->id, 0),
                'marketsBreakdown' => ($marketsBreakdown->get($sector->id) ?? collect())->toArray(),
            ]);

        return Inertia::render('Sectors', [
            'sectors' => $sectors,
            'markets' => $markets->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
            ]),
        ]);
    }

    public function show(string $locale, Sector $sector, Request $request): Response
    {
        $sector->loadCount('assets');

        // Use cached data for markets breakdown
        $sectorAssets = StaticDataCacheService::assetsBySector($sector->id);
        $markets = StaticDataCacheService::markets();

        $marketsBreakdown = $sectorAssets
            ->groupBy('market_id')
            ->map(function ($assets, $marketId) use ($markets) {
                $market = $markets->firstWhere('id', $marketId);

                return [
                    'marketId' => $marketId,
                    'marketCode' => $market?->code,
                    'marketName' => $market?->name,
                    'count' => $assets->count(),
                ];
            })
            ->values();

        return Inertia::render('sectors/Show', [
            'sector' => [
                'id' => $sector->id,
                'name' => $sector->name,
                'description' => app()->getLocale() === 'ar'
                    ? $sector->description_ar
                    : $sector->description_en,
                'assetCount' => $sector->assets_count,
                'predictionCount' => PredictionStatsService::countForSector($sector->id),
                'marketsBreakdown' => $marketsBreakdown->toArray(),
            ],
            'markets' => $markets->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
            ]),
            'filters' => [
                'marketId' => $request->input('market_id'),
                'search' => $request->input('search'),
            ],
            'assets' => Inertia::defer(fn () => $this->getSectorAssets($sector, $request)),
        ]);
    }

    private function getSectorAssets(Sector $sector, Request $request): array
    {
        $search = $request->input('search');
        $marketId = $request->input('market_id');
        $page = max(1, (int) $request->input('page', 1));

        // Use SearchService for server-side search
        if ($search) {
            $results = SearchService::searchAssetsInSector($sector->id, $search, 10, $page);

            // Filter by market if specified (post-search filter)
            $data = collect($results->items());
            if ($marketId) {
                $data = $data->filter(fn ($asset) => $asset->market_id === $marketId)->values();
            }

            return [
                'data' => $data->map(fn ($asset) => [
                    'id' => $asset->id,
                    'symbol' => $asset->symbol,
                    'name' => $asset->name,
                    'market' => $asset->market ? [
                        'id' => $asset->market->id,
                        'code' => $asset->market->code,
                        'name' => $asset->market->name,
                    ] : null,
                    'latestPrice' => $asset->cachedPrice ? [
                        'last' => $asset->cachedPrice->price,
                        'pcp' => $asset->cachedPrice->percent_change,
                        'freshness' => $asset->cachedPrice->freshness,
                        'hoursAgo' => $asset->cachedPrice->hours_ago,
                    ] : null,
                    'latestPrediction' => $asset->cachedPrediction ? [
                        'predictedPrice' => $asset->cachedPrediction->price_prediction,
                        'confidence' => $asset->cachedPrediction->confidence,
                        'horizon' => $asset->cachedPrediction->horizon,
                        'horizonLabel' => $asset->cachedPrediction->horizon_label,
                        'freshness' => $asset->cachedPrediction->freshness,
                    ] : null,
                ])->toArray(),
                'meta' => PaginationHelper::meta($results),
            ];
        }

        // Default: no search, just filter and paginate
        $query = Asset::where('sector_id', $sector->id)
            ->with(['market', 'cachedPrice', 'cachedPrediction']);

        if ($marketId) {
            $query->where('market_id', $marketId);
        }

        $assets = $query->paginate(10, ['*'], 'page', $page);

        return [
            'data' => $assets->map(fn ($asset) => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'market' => $asset->market ? [
                    'id' => $asset->market->id,
                    'code' => $asset->market->code,
                    'name' => $asset->market->name,
                ] : null,
                'latestPrice' => $asset->cachedPrice ? [
                    'last' => $asset->cachedPrice->price,
                    'pcp' => $asset->cachedPrice->percent_change,
                    'freshness' => $asset->cachedPrice->freshness,
                    'hoursAgo' => $asset->cachedPrice->hours_ago,
                ] : null,
                'latestPrediction' => $asset->cachedPrediction ? [
                    'predictedPrice' => $asset->cachedPrediction->price_prediction,
                    'confidence' => $asset->cachedPrediction->confidence,
                    'horizon' => $asset->cachedPrediction->horizon,
                    'horizonLabel' => $asset->cachedPrediction->horizon_label,
                    'freshness' => $asset->cachedPrediction->freshness,
                ] : null,
            ])->toArray(),
            'meta' => PaginationHelper::meta($assets),
        ];
    }
}
