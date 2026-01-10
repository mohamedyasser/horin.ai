<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Country;
use App\Models\LatestRecommendation;
use App\Models\Market;
use App\Models\Sector;
use App\Support\PaginationHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecommendationController extends Controller
{
    public function index(Request $request): Response
    {
        $markets = Market::with('country')
            ->select('id', 'code', 'name_en', 'name_ar', 'country_id')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'country' => $m->country ? [
                    'id' => $m->country->id,
                    'name' => $m->country->name,
                    'code' => $m->country->code,
                ] : null,
            ]);

        $sectors = Sector::select('id', 'name_en', 'name_ar')
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]);

        $countries = Country::whereHas('markets')
            ->get()
            ->map(fn ($country) => [
                'id' => $country->id,
                'name' => $country->name,
                'code' => $country->code,
            ]);

        $filters = [
            'search' => $request->input('search'),
            'market' => $request->input('market'),
            'sector' => $request->input('sector'),
            'country' => $request->input('country'),
            'recommendation' => $request->input('recommendation'),
        ];

        return Inertia::render('Recommendations', [
            'markets' => $markets,
            'sectors' => $sectors,
            'countries' => $countries,
            'filters' => $filters,
            'recommendations' => Inertia::defer(fn () => $this->getRecommendations($request)),
            'topBuySignals' => Inertia::defer(fn () => $this->getTopBuySignals()),
            'topSellSignals' => Inertia::defer(fn () => $this->getTopSellSignals()),
        ]);
    }

    private function getRecommendations(Request $request): array
    {
        $marketFilter = $request->input('market');
        $sectorFilter = $request->input('sector');
        $countryFilter = $request->input('country');
        $searchFilter = $request->input('search');
        $recommendationFilter = $request->input('recommendation');

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
                return [
                    'data' => [],
                    'meta' => PaginationHelper::empty(),
                ];
            }
        }

        // Build base query using LatestRecommendation
        $query = LatestRecommendation::with(['asset.market', 'asset.cachedPrice', 'asset.sector']);

        // Apply market filter
        if ($marketFilter) {
            $market = Market::where('code', $marketFilter)->first();
            if ($market) {
                $query->whereHas('asset', fn ($q) => $q->where('market_id', $market->id));
            }
        }

        // Apply sector filter
        if ($sectorFilter) {
            $sector = Sector::find($sectorFilter);
            if ($sector) {
                $query->whereHas('asset', fn ($q) => $q->where('sector_id', $sector->id));
            }
        }

        // Apply country filter
        if ($countryFilter) {
            $country = Country::find($countryFilter);
            if ($country) {
                $query->whereHas('asset.market', fn ($q) => $q->where('country_id', $country->id));
            }
        }

        // Apply recommendation type filter
        if ($recommendationFilter) {
            $query->where('recommendation', $recommendationFilter);
        }

        // Apply Scout search results filter
        if ($searchAssetIds !== null) {
            $query->whereIn('pid', $searchAssetIds);
        }

        // Order by score (highest first)
        $query->orderByDesc('score');

        // Paginate results
        $recommendations = $query->paginate(20);

        $data = $recommendations
            ->filter(fn ($r) => $r->asset !== null && $r->asset->market !== null)
            ->map(fn ($r) => $this->formatRecommendation($r));

        return [
            'data' => $data->values()->toArray(),
            'meta' => PaginationHelper::meta($recommendations),
        ];
    }

    private function formatRecommendation($recommendation): array
    {
        $currentPrice = $recommendation->asset->cachedPrice?->price ?? 0;

        return [
            'id' => $recommendation->id,
            'pid' => $recommendation->pid,
            'asset' => [
                'id' => $recommendation->asset->id,
                'symbol' => $recommendation->asset->symbol,
                'name' => $recommendation->asset->name,
                'market' => $recommendation->asset->market ? [
                    'id' => $recommendation->asset->market->id,
                    'code' => $recommendation->asset->market->code,
                    'name' => $recommendation->asset->market->name,
                ] : null,
                'sector' => $recommendation->asset->sector ? [
                    'id' => $recommendation->asset->sector->id,
                    'name' => $recommendation->asset->sector->name,
                ] : null,
                'currentPrice' => $currentPrice > 0 ? (float) $currentPrice : null,
                'priceChange' => $recommendation->asset->cachedPrice?->percent_change ?? null,
            ],
            'score' => (float) $recommendation->score,
            'recommendation' => $recommendation->recommendation,
            'createdAt' => $recommendation->created_at?->toISOString(),
        ];
    }

    private function getTopBuySignals(): array
    {
        return LatestRecommendation::with(['asset.market'])
            ->whereIn('recommendation', ['STRONG_BUY', 'BUY'])
            ->orderByDesc('score')
            ->limit(5)
            ->get()
            ->filter(fn ($r) => $r->asset !== null && $r->asset->market !== null)
            ->map(fn ($r) => [
                'id' => $r->id,
                'pid' => $r->pid,
                'score' => (float) $r->score,
                'recommendation' => $r->recommendation,
                'asset' => [
                    'id' => $r->asset->id,
                    'symbol' => $r->asset->symbol,
                    'name' => $r->asset->name,
                    'market' => ['code' => $r->asset->market->code],
                ],
            ])
            ->values()
            ->toArray();
    }

    private function getTopSellSignals(): array
    {
        return LatestRecommendation::with(['asset.market'])
            ->whereIn('recommendation', ['STRONG_SELL', 'SELL'])
            ->orderByDesc('score')
            ->limit(5)
            ->get()
            ->filter(fn ($r) => $r->asset !== null && $r->asset->market !== null)
            ->map(fn ($r) => [
                'id' => $r->id,
                'pid' => $r->pid,
                'score' => (float) $r->score,
                'recommendation' => $r->recommendation,
                'asset' => [
                    'id' => $r->asset->id,
                    'symbol' => $r->asset->symbol,
                    'name' => $r->asset->name,
                    'market' => ['code' => $r->asset->market->code],
                ],
            ])
            ->values()
            ->toArray();
    }
}
