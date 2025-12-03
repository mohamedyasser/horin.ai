<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Market;
use App\Services\PredictionStatsService;
use App\Support\PaginationHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketController extends Controller
{
    public function index(): Response
    {
        $predictionCounts = PredictionStatsService::countsByMarket();

        $markets = Market::with('country')
            ->withCount('assets')
            ->get()
            ->map(fn ($market) => [
                'id' => $market->id,
                'name' => $market->name,
                'code' => $market->code,
                'country' => $market->country ? [
                    'id' => $market->country->id,
                    'name' => $market->country->name,
                    'code' => $market->country->code,
                ] : null,
                'isOpen' => $market->isOpenNow(),
                'assetCount' => $market->assets_count,
                'predictionCount' => $predictionCounts->get($market->id, 0),
            ]);

        return Inertia::render('Markets', [
            'markets' => $markets,
        ]);
    }

    public function show(string $locale, Market $market, Request $request): Response
    {
        $market->load('country');
        $market->loadCount('assets');

        return Inertia::render('markets/Show', [
            'market' => [
                'id' => $market->id,
                'name' => $market->name,
                'code' => $market->code,
                'country' => $market->country ? [
                    'id' => $market->country->id,
                    'name' => $market->country->name,
                    'code' => $market->country->code,
                ] : null,
                'isOpen' => $market->isOpenNow(),
                'openAt' => $market->open_at,
                'closeAt' => $market->close_at,
                'tvLink' => $market->tv_link,
                'assetCount' => $market->assets_count,
                'predictionCount' => PredictionStatsService::countForMarket($market->id),
            ],
            'assets' => Inertia::defer(fn () => $this->getMarketAssets($market)),
        ]);
    }

    private function getMarketAssets(Market $market): array
    {
        $assets = Asset::where('market_id', $market->id)
            ->with(['sector', 'cachedPrice', 'cachedPrediction'])
            ->paginate(10);

        return [
            'data' => $assets->map(fn ($asset) => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'sector' => $asset->sector ? [
                    'id' => $asset->sector->id,
                    'name' => $asset->sector->name,
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
