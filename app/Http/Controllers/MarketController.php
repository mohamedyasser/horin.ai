<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Market;
use App\Services\PredictionStatsService;
use App\Support\Horizon;
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
            ->with(['sector', 'latestPrice', 'latestPrediction'])
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
                'latestPrice' => $asset->latestPrice ? [
                    'last' => (float) $asset->latestPrice->last,
                    'pcp' => $asset->latestPrice->pcp,
                ] : null,
                'latestPrediction' => $asset->latestPrediction ? [
                    'predictedPrice' => $asset->latestPrediction->price_prediction,
                    'confidence' => $asset->latestPrediction->confidence,
                    'horizon' => $asset->latestPrediction->horizon,
                    'horizonLabel' => Horizon::label($asset->latestPrediction->horizon),
                ] : null,
            ])->toArray(),
            'meta' => PaginationHelper::meta($assets),
        ];
    }
}
