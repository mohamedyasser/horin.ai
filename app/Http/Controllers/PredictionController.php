<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Country;
use App\Models\LatestPrediction;
use App\Models\Market;
use App\Models\Sector;
use App\Support\Horizon;
use App\Support\PaginationHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PredictionController extends Controller
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
        ];

        return Inertia::render('Predictions', [
            'markets' => $markets,
            'sectors' => $sectors,
            'countries' => $countries,
            'filters' => $filters,
            'predictions' => Inertia::defer(fn () => $this->getPredictions($request)),
        ]);
    }

    private function getPredictions(Request $request): array
    {
        $marketFilter = $request->input('market');
        $sectorFilter = $request->input('sector');
        $countryFilter = $request->input('country');
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
                return [
                    'data' => [],
                    'meta' => PaginationHelper::empty(),
                ];
            }
        }

        // Build base query using LatestPrediction (unique per asset)
        $query = LatestPrediction::with(['asset.market', 'asset.cachedPrice', 'asset.sector']);

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

        // Apply Scout search results filter
        if ($searchAssetIds !== null) {
            $query->whereIn('pid', $searchAssetIds);
        }

        // Order by confidence (highest first)
        $query->orderByDesc('confidence');

        // Paginate results
        $predictions = $query->paginate(20);

        $data = $predictions
            ->filter(fn ($p) => $p->asset !== null)
            ->map(fn ($p) => $this->formatPrediction($p));

        return [
            'data' => $data->values()->toArray(),
            'meta' => PaginationHelper::meta($predictions),
        ];
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
                'market' => $prediction->asset->market ? [
                    'id' => $prediction->asset->market->id,
                    'code' => $prediction->asset->market->code,
                    'name' => $prediction->asset->market->name,
                ] : null,
                'sector' => $prediction->asset->sector ? [
                    'id' => $prediction->asset->sector->id,
                    'name' => $prediction->asset->sector->name,
                ] : null,
                'currentPrice' => $currentPrice > 0 ? (float) $currentPrice : null,
            ],
            'predictedPrice' => (float) $prediction->price_prediction,
            'expectedGainPercent' => round($expectedGain, 2),
            'confidence' => (float) $prediction->confidence,
            'horizon' => $prediction->horizon,
            'horizonLabel' => Horizon::label($prediction->horizon),
            'timestamp' => $timestamp?->toISOString(),
            'targetTimestamp' => $targetTimestamp,
            'freshness' => $prediction->freshness ?? null,
        ];
    }
}
