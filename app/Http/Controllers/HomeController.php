<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\LatestPrediction;
use App\Models\Market;
use App\Models\PredictedAssetPrice;
use App\Models\Sector;
use App\Services\PredictionStatsService;
use App\Support\Horizon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $predictionCountsByMarket = PredictionStatsService::countsByMarket();
        $predictionCountsBySector = PredictionStatsService::countsBySector();

        $stats = [
            'totalMarkets' => Market::count(),
            'totalAssets' => Asset::count(),
            'totalPredictions' => PredictedAssetPrice::count(),
            'totalSectors' => Sector::count(),
        ];

        $markets = Market::with('country')
            ->withCount('assets')
            ->get()
            ->map(fn ($market) => [
                'id' => $market->id,
                'name' => $market->name,
                'code' => $market->code,
                'country' => [
                    'id' => $market->country->id,
                    'name' => $market->country->name,
                    'code' => $market->country->code,
                ],
                'isOpen' => $market->isOpenNow(),
                'assetCount' => $market->assets_count,
                'predictionCount' => $predictionCountsByMarket->get($market->id, 0),
            ]);

        $sectors = Sector::withCount('assets')
            ->get()
            ->map(fn ($sector) => [
                'id' => $sector->id,
                'name' => $sector->name,
                'assetCount' => $sector->assets_count,
                'predictionCount' => $predictionCountsBySector->get($sector->id, 0),
            ]);

        return Inertia::render('Welcome', [
            'stats' => $stats,
            'markets' => $markets,
            'sectors' => $sectors,
            'filters' => [
                'search' => $request->input('search'),
                'market' => $request->input('market'),
            ],
            'featuredPredictions' => Inertia::defer(fn () => $this->getFeaturedPredictions($request)),
            'topMovers' => Inertia::defer(fn () => $this->getTopMovers()),
            'recentPredictions' => Inertia::defer(fn () => $this->getRecentPredictions()),
        ]);
    }

    private function getFeaturedPredictions(Request $request): array
    {
        $marketFilter = $request->input('market');
        $searchFilter = $request->input('search');

        // If search filter provided, use Scout to find matching asset IDs first
        $searchAssetIds = null;
        if ($searchFilter) {
            $searchAssetIds = Asset::search($searchFilter)
                ->take(100)
                ->get()
                ->pluck('inv_id')
                ->toArray();

            // No matching assets found
            if (empty($searchAssetIds)) {
                return ['data' => []];
            }
        }

        // Get all markets
        $markets = Market::all();

        $allPredictions = collect();

        // When "All Markets" is selected, show 2 per market; when specific market, show 10
        $limitPerMarket = $marketFilter ? 10 : 2;

        foreach ($markets as $market) {
            // Skip if market filter is set and doesn't match
            if ($marketFilter && $market->code !== $marketFilter) {
                continue;
            }

            // Use LatestPrediction (materialized view) for better performance
            $query = LatestPrediction::with(['asset.market', 'asset.cachedPrice'])
                ->whereHas('asset', fn ($q) => $q->where('market_id', $market->id));

            // Apply Scout search results filter
            if ($searchAssetIds !== null) {
                $query->whereIn('pid', $searchAssetIds);
            }

            $query->orderByDesc('timestamp')
                ->limit($limitPerMarket);

            $predictions = $query->get()->filter(fn ($p) => $p->asset !== null);

            $allPredictions = $allPredictions->concat($predictions);
        }

        // Sort all combined predictions by timestamp (newest first)
        $sorted = $allPredictions->sortByDesc('timestamp')->values();

        return [
            'data' => $sorted->map(fn ($p) => $this->formatPrediction($p))->toArray(),
        ];
    }

    private function getTopMovers(): array
    {
        return Asset::with(['market', 'cachedPrice'])
            ->whereHas('cachedPrice')
            ->get()
            ->sortByDesc(fn ($a) => (float) str_replace('%', '', $a->cachedPrice->percent_change ?? '0'))
            ->take(5)
            ->map(fn ($asset) => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'market' => ['code' => $asset->market->code],
                'currentPrice' => $asset->cachedPrice->price,
                'priceChangePercent' => (float) str_replace('%', '', $asset->cachedPrice->percent_change ?? '0'),
                'freshness' => $asset->cachedPrice->freshness,
            ])
            ->values()
            ->toArray();
    }

    private function getRecentPredictions(): array
    {
        return LatestPrediction::with('asset')
            ->orderByDesc('timestamp')
            ->limit(5)
            ->get()
            ->filter(fn ($p) => $p->asset !== null)
            ->map(function ($p) {
                $timestamp = $p->timestamp ? Carbon::createFromTimestamp($p->timestamp) : null;
                $horizonMinutes = $p->horizon_minutes ?? Horizon::minutes($p->horizon);
                $targetTimestamp = $timestamp && $horizonMinutes > 0
                    ? $timestamp->copy()->addMinutes($horizonMinutes)->toISOString()
                    : null;

                return [
                    'id' => $p->pid.'-'.$p->timestamp,
                    'asset' => [
                        'id' => $p->asset->id,
                        'symbol' => $p->asset->symbol,
                        'name' => $p->asset->name,
                    ],
                    'predictedPrice' => (float) $p->price_prediction,
                    'confidence' => (float) $p->confidence,
                    'horizon' => $p->horizon,
                    'horizonLabel' => Horizon::label($p->horizon),
                    'timestamp' => $timestamp?->toISOString(),
                    'targetTimestamp' => $targetTimestamp,
                    'freshness' => $p->freshness,
                ];
            })
            ->toArray();
    }

    private function formatPrediction($prediction): array
    {
        // Use cachedPrice (from materialized view) for better performance
        $currentPrice = $prediction->asset->cachedPrice?->price ?? 0;
        $expectedGain = $currentPrice > 0
            ? (($prediction->price_prediction - $currentPrice) / $currentPrice) * 100
            : 0;

        // Use pre-computed fields from materialized view when available
        $timestamp = $prediction->timestamp ? Carbon::createFromTimestamp($prediction->timestamp) : null;
        $horizonMinutes = $prediction->horizon_minutes ?? Horizon::minutes($prediction->horizon);
        $targetTimestamp = $timestamp && $horizonMinutes > 0
            ? $timestamp->copy()->addMinutes($horizonMinutes)->toISOString()
            : null;

        return [
            'id' => $prediction->pid.'-'.$prediction->timestamp,
            'asset' => [
                'id' => $prediction->asset->id,
                'symbol' => $prediction->asset->symbol,
                'name' => $prediction->asset->name,
                'market' => ['code' => $prediction->asset->market->code],
            ],
            'currentPrice' => $currentPrice > 0 ? (float) $currentPrice : null,
            'predictedPrice' => (float) $prediction->price_prediction,
            'confidence' => (float) $prediction->confidence,
            'horizon' => $prediction->horizon,
            'horizonLabel' => Horizon::label($prediction->horizon),
            'expectedGainPercent' => round($expectedGain, 2),
            'timestamp' => $timestamp?->toISOString(),
            'targetTimestamp' => $targetTimestamp,
            'freshness' => $prediction->freshness ?? null,
        ];
    }
}
