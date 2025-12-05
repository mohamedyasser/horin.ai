<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Market;
use App\Models\PredictedAssetPrice;
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
        $filterOptions = [
            'markets' => Market::select('id', 'code', 'name_en', 'name_ar')
                ->get()
                ->map(fn ($m) => ['id' => $m->id, 'code' => $m->code, 'name' => $m->name]),
            'sectors' => Sector::select('id', 'name_en', 'name_ar')
                ->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'horizons' => Horizon::options(),
        ];

        $filters = [
            'search' => $request->input('search'),
            'marketId' => $request->input('market_id'),
            'sectorId' => $request->input('sector_id'),
            'horizon' => $request->input('horizon'),
            'minConfidence' => (int) $request->input('min_confidence', 0),
        ];

        $sort = [
            'field' => in_array($request->input('sort'), ['confidence', 'timestamp']) ? $request->input('sort') : 'confidence',
            'direction' => $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc',
        ];

        return Inertia::render('Predictions', [
            'filterOptions' => $filterOptions,
            'filters' => $filters,
            'sort' => $sort,
            'predictions' => Inertia::defer(fn () => $this->getPredictions($filters, $sort)),
        ]);
    }

    private function getPredictions(array $filters, array $sort): array
    {
        $query = PredictedAssetPrice::with([
            'asset.market',
            'asset.sector',
            'asset.latestPrice',
        ]);

        // Use Scout for search - pluck inv_id to match pid column
        if ($filters['search']) {
            $searchAssetIds = Asset::search($filters['search'])
                ->take(100)
                ->get()
                ->pluck('inv_id')
                ->toArray();

            if (empty($searchAssetIds)) {
                return [
                    'data' => [],
                    'meta' => PaginationHelper::empty(),
                ];
            }

            $query->whereIn('pid', $searchAssetIds);
        }
        if ($filters['marketId']) {
            $query->whereHas('asset', fn ($q) => $q->where('market_id', $filters['marketId']));
        }
        if ($filters['sectorId']) {
            $query->whereHas('asset', fn ($q) => $q->where('sector_id', $filters['sectorId']));
        }
        if ($filters['horizon']) {
            $query->where('horizon', $filters['horizon']);
        }
        if ($filters['minConfidence'] > 0) {
            $query->where('confidence', '>=', $filters['minConfidence']);
        }

        $sortField = $sort['field'] === 'timestamp' ? 'timestamp' : 'confidence';
        $query->orderBy($sortField, $sort['direction']);

        $predictions = $query->paginate(10);

        $data = $predictions
            ->filter(fn ($p) => $p->asset !== null)
            ->map(function ($p) {
                $currentPrice = $p->asset->latestPrice?->last;
                $expectedGain = $currentPrice && $currentPrice > 0
                    ? (($p->price_prediction - $currentPrice) / $currentPrice) * 100
                    : 0;

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
                        'market' => $p->asset->market ? [
                            'id' => $p->asset->market->id,
                            'code' => $p->asset->market->code,
                            'name' => $p->asset->market->name,
                        ] : null,
                        'sector' => $p->asset->sector ? [
                            'id' => $p->asset->sector->id,
                            'name' => $p->asset->sector->name,
                        ] : null,
                        'currentPrice' => $currentPrice ? (float) $currentPrice : null,
                    ],
                    'predictedPrice' => $p->price_prediction,
                    'expectedGainPercent' => round($expectedGain, 2),
                    'confidence' => $p->confidence,
                    'horizon' => $p->horizon,
                    'horizonLabel' => Horizon::label($p->horizon),
                    'timestamp' => $timestamp?->toISOString(),
                    'targetTimestamp' => $targetTimestamp,
                ];
            });

        return [
            'data' => $data->values()->toArray(),
            'meta' => PaginationHelper::meta($predictions),
        ];
    }
}
