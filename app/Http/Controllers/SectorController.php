<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Market;
use App\Models\Sector;
use App\Services\PredictionStatsService;
use App\Support\Horizon;
use App\Support\PaginationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SectorController extends Controller
{
    public function index(): Response
    {
        $predictionCounts = PredictionStatsService::countsBySector();

        $marketsBreakdown = DB::table('assets')
            ->join('markets', 'assets.market_id', '=', 'markets.id')
            ->whereNotNull('assets.sector_id')
            ->select(
                'assets.sector_id',
                'markets.id as market_id',
                'markets.code as market_code',
                'markets.name_en',
                'markets.name_ar',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('assets.sector_id', 'markets.id', 'markets.code', 'markets.name_en', 'markets.name_ar')
            ->get()
            ->groupBy('sector_id');

        $sectors = Sector::withCount('assets')
            ->get()
            ->map(fn ($sector) => [
                'id' => $sector->id,
                'name' => $sector->name,
                'description' => app()->getLocale() === 'ar'
                    ? $sector->description_ar
                    : $sector->description_en,
                'assetCount' => $sector->assets_count,
                'predictionCount' => $predictionCounts->get($sector->id, 0),
                'marketsBreakdown' => ($marketsBreakdown->get($sector->id) ?? collect())
                    ->map(fn ($row) => [
                        'marketId' => $row->market_id,
                        'marketCode' => $row->market_code,
                        'marketName' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en,
                        'count' => $row->count,
                    ])->toArray(),
            ]);

        return Inertia::render('Sectors', [
            'sectors' => $sectors,
        ]);
    }

    public function show(string $locale, Sector $sector, Request $request): Response
    {
        $sector->loadCount('assets');

        $marketsBreakdown = DB::table('assets')
            ->join('markets', 'assets.market_id', '=', 'markets.id')
            ->where('assets.sector_id', $sector->id)
            ->select(
                'markets.id as market_id',
                'markets.code as market_code',
                'markets.name_en',
                'markets.name_ar',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('markets.id', 'markets.code', 'markets.name_en', 'markets.name_ar')
            ->get();

        $markets = Market::select('id', 'code', 'name_en', 'name_ar')->get();

        return Inertia::render('sectors/Show', [
            'sector' => [
                'id' => $sector->id,
                'name' => $sector->name,
                'description' => app()->getLocale() === 'ar'
                    ? $sector->description_ar
                    : $sector->description_en,
                'assetCount' => $sector->assets_count,
                'predictionCount' => PredictionStatsService::countForSector($sector->id),
                'marketsBreakdown' => $marketsBreakdown->map(fn ($row) => [
                    'marketId' => $row->market_id,
                    'marketCode' => $row->market_code,
                    'marketName' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en,
                    'count' => $row->count,
                ])->toArray(),
            ],
            'markets' => $markets->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
            ]),
            'filters' => [
                'marketId' => $request->input('market_id'),
            ],
            'assets' => Inertia::defer(fn () => $this->getSectorAssets($sector, $request)),
        ]);
    }

    private function getSectorAssets(Sector $sector, Request $request): array
    {
        $query = Asset::where('sector_id', $sector->id)
            ->with(['market', 'latestPrice', 'latestPrediction']);

        if ($request->filled('market_id')) {
            $query->where('market_id', $request->input('market_id'));
        }

        $assets = $query->paginate(10);

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
