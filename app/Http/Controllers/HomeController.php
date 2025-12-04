<?php

namespace App\Http\Controllers;

use App\Models\Asset;
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
            'featuredPredictions' => Inertia::defer(fn () => $this->getFeaturedPredictions($request)),
            'topMovers' => Inertia::defer(fn () => $this->getTopMovers()),
            'recentPredictions' => Inertia::defer(fn () => $this->getRecentPredictions()),
        ]);
    }

    private function getFeaturedPredictions(Request $request): array
    {
        $marketFilter = $request->input('market');

        // Get all markets
        $markets = Market::all();

        $allPredictions = collect();

        foreach ($markets as $market) {
            // Skip if market filter is set and doesn't match
            if ($marketFilter && $market->code !== $marketFilter) {
                continue;
            }

            // Get last 10 predictions for this market (latest by timestamp)
            // Use cachedPrice (materialized view) for better performance
            $predictions = PredictedAssetPrice::with(['asset.market', 'asset.cachedPrice'])
                ->whereHas('asset', fn ($q) => $q->where('market_id', $market->id))
                ->orderByDesc('timestamp')
                ->limit(10)
                ->get()
                ->filter(fn ($p) => $p->asset !== null);

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
        return PredictedAssetPrice::with('asset')
            ->orderByDesc('timestamp')
            ->limit(5)
            ->get()
            ->filter(fn ($p) => $p->asset !== null)
            ->map(function ($p) {
                // timestamp is in seconds, not milliseconds
                $timestamp = $p->timestamp ? Carbon::createFromTimestamp($p->timestamp) : null;
                $horizonMinutes = Horizon::minutes($p->horizon);
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

        // timestamp is in seconds, not milliseconds
        $timestamp = $prediction->timestamp ? Carbon::createFromTimestamp($prediction->timestamp) : null;
        $horizonMinutes = Horizon::minutes($prediction->horizon);
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
        ];
    }
}
