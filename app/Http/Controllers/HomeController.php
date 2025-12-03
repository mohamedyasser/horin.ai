<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Market;
use App\Models\PredictedAssetPrice;
use App\Models\Sector;
use App\Services\PredictionStatsService;
use App\Support\Horizon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $sortBy = $request->input('sort', 'confidence');
        $sortBy = in_array($sortBy, ['confidence', 'timestamp']) ? $sortBy : 'confidence';

        return PredictedAssetPrice::with(['asset.market', 'asset.latestPrice'])
            ->orderByDesc($sortBy)
            ->limit(5)
            ->get()
            ->filter(fn ($p) => $p->asset !== null)
            ->map(fn ($p) => $this->formatPrediction($p))
            ->toArray();
    }

    private function getTopMovers(): array
    {
        // LATERAL JOIN efficiently finds latest price per asset using index
        $results = DB::select("
            SELECT a.id, a.symbol, a.name_en, a.name_ar, m.code as market_code,
                   ap.last as current_price, ap.pcp as price_change_percent
            FROM assets a
            JOIN markets m ON a.market_id = m.id
            JOIN LATERAL (
                SELECT last, pcp
                FROM asset_prices
                WHERE pid = a.inv_id
                ORDER BY timestamp DESC
                LIMIT 1
            ) ap ON true
            ORDER BY NULLIF(REPLACE(ap.pcp, '%', ''), '')::DECIMAL DESC NULLS LAST
            LIMIT 5
        ");

        return collect($results)
            ->map(fn ($row) => [
                'id' => $row->id,
                'symbol' => $row->symbol,
                'name' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en,
                'market' => ['code' => $row->market_code],
                'currentPrice' => (float) $row->current_price,
                'priceChangePercent' => (float) str_replace('%', '', $row->price_change_percent),
            ])
            ->toArray();
    }

    private function getRecentPredictions(): array
    {
        return PredictedAssetPrice::with('asset')
            ->orderByDesc('timestamp')
            ->limit(5)
            ->get()
            ->filter(fn ($p) => $p->asset !== null)
            ->map(fn ($p) => [
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
                'timestamp' => $p->created_at?->toISOString(),
            ])
            ->toArray();
    }

    private function formatPrediction($prediction): array
    {
        $currentPrice = $prediction->asset->latestPrice?->last ?? 0;
        $expectedGain = $currentPrice > 0
            ? (($prediction->price_prediction - $currentPrice) / $currentPrice) * 100
            : 0;

        return [
            'id' => $prediction->pid.'-'.$prediction->timestamp,
            'asset' => [
                'id' => $prediction->asset->id,
                'symbol' => $prediction->asset->symbol,
                'name' => $prediction->asset->name,
                'market' => ['code' => $prediction->asset->market->code],
            ],
            'predictedPrice' => (float) $prediction->price_prediction,
            'confidence' => (float) $prediction->confidence,
            'horizon' => $prediction->horizon,
            'horizonLabel' => Horizon::label($prediction->horizon),
            'expectedGainPercent' => round($expectedGain, 2),
            'timestamp' => $prediction->created_at?->toISOString(),
        ];
    }
}
