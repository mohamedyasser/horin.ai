<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\InstantIndicator;
use App\Models\PredictedAssetPrice;
use App\Support\Horizon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function show(string $locale, Asset $asset, Request $request): Response
    {
        $asset->load(['market', 'sector', 'country', 'latestPrice']);
        $chartPeriod = in_array((int) $request->input('period', 30), [30, 90, 365]) ? (int) $request->input('period', 30) : 30;

        return Inertia::render('assets/Show', [
            'asset' => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'type' => $asset->type,
                'currency' => $asset->currency,
                'market' => $asset->market ? [
                    'id' => $asset->market->id,
                    'code' => $asset->market->code,
                    'name' => $asset->market->name,
                ] : null,
                'sector' => $asset->sector ? [
                    'id' => $asset->sector->id,
                    'name' => $asset->sector->name,
                ] : null,
                'country' => $asset->country ? [
                    'id' => $asset->country->id,
                    'name' => $asset->country->name,
                    'code' => $asset->country->code,
                ] : null,
            ],
            'price' => $asset->latestPrice ? [
                'last' => (float) $asset->latestPrice->last,
                'changePercent' => $asset->latestPrice->pcp,
                'high' => (float) $asset->latestPrice->high,
                'low' => (float) $asset->latestPrice->low,
                'previousClose' => (float) $asset->latestPrice->last_close,
                'volume' => $asset->latestPrice->turnover,
                'updatedAt' => Carbon::createFromTimestampMs($asset->latestPrice->timestamp)->toISOString(),
            ] : null,
            'chartPeriod' => $chartPeriod,
            'predictions' => Inertia::lazy(fn () => $this->getAssetPredictions($asset)),
            'indicators' => Inertia::lazy(fn () => $this->getAssetIndicators($asset)),
            'priceHistory' => Inertia::lazy(fn () => $this->getPriceHistory($asset, $chartPeriod)),
            'predictionHistory' => Inertia::lazy(fn () => $this->getPredictionHistory($asset)),
        ]);
    }

    private function getAssetPredictions(Asset $asset): array
    {
        $currentPrice = $asset->latestPrice?->last ?? 0;

        return PredictedAssetPrice::where('pid', $asset->inv_id)
            ->whereIn('horizon', Horizon::ALL)
            ->orderByDesc('timestamp')
            ->get()
            ->groupBy('horizon')
            ->map(function ($group) use ($currentPrice) {
                $p = $group->first();
                $expectedGain = $currentPrice > 0
                    ? (($p->price_prediction - $currentPrice) / $currentPrice) * 100
                    : 0;

                return [
                    'horizon' => $p->horizon,
                    'horizonLabel' => Horizon::label($p->horizon),
                    'predictedPrice' => $p->price_prediction,
                    'confidence' => $p->confidence,
                    'expectedGainPercent' => round($expectedGain, 2),
                    'timestamp' => $p->created_at?->toISOString(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function getAssetIndicators(Asset $asset): ?array
    {
        $indicator = InstantIndicator::where('pid', $asset->inv_id)
            ->orderByDesc('timestamp')
            ->first();

        if (! $indicator) {
            return null;
        }

        return [
            'rsi' => $indicator->rsi,
            'macd' => [
                'line' => $indicator->macd_line,
                'signal' => $indicator->macd_signal,
                'histogram' => $indicator->macd_histogram,
            ],
            'ema' => $indicator->ema,
            'sma' => $indicator->sma,
            'atr' => $indicator->atr,
            'bollingerBands' => [
                'upper' => $indicator->bb_upper,
                'middle' => $indicator->bb_middle,
                'lower' => $indicator->bb_lower,
            ],
            'updatedAt' => Carbon::createFromTimestampMs($indicator->timestamp)->toISOString(),
        ];
    }

    private function getPriceHistory(Asset $asset, int $days): array
    {
        $startTimestamp = now()->subDays($days)->timestamp * 1000;

        return AssetPrice::where('pid', $asset->inv_id)
            ->where('timestamp', '>=', $startTimestamp)
            ->orderBy('timestamp')
            ->get()
            ->map(fn ($price) => [
                'timestamp' => $price->timestamp,
                'close' => (float) $price->last,
                'high' => (float) $price->high,
                'low' => (float) $price->low,
                'open' => (float) $price->last_close,
                'volume' => (float) ($price->turnover_numeric ?? 0),
            ])
            ->toArray();
    }

    private function getPredictionHistory(Asset $asset): array
    {
        return PredictedAssetPrice::where('pid', $asset->inv_id)
            ->orderByDesc('timestamp')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'predictedPrice' => $p->price_prediction,
                'confidence' => $p->confidence,
                'horizon' => $p->horizon,
                'horizonLabel' => Horizon::label($p->horizon),
                'timestamp' => $p->created_at?->toISOString(),
            ])
            ->toArray();
    }
}
