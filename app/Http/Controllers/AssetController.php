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
        $asset->load(['market', 'sector', 'country', 'cachedPrice']);
        $chartPeriod = in_array((int) $request->input('period', 7), [7, 30, 90, 180]) ? (int) $request->input('period', 7) : 7;

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
            'price' => $asset->cachedPrice ? [
                'last' => $asset->cachedPrice->price,
                'changePercent' => $asset->cachedPrice->percent_change,
                'high' => $asset->cachedPrice->high,
                'low' => $asset->cachedPrice->low,
                'previousClose' => $asset->cachedPrice->last_close,
                'volume' => $asset->cachedPrice->volume,
                'updatedAt' => $asset->cachedPrice->price_time?->toISOString(),
                'freshness' => $asset->cachedPrice->freshness,
                'hoursAgo' => $asset->cachedPrice->hours_ago,
            ] : null,
            'chartPeriod' => $chartPeriod,
            'predictions' => Inertia::defer(fn () => $this->getAssetPredictions($asset)),
            'indicators' => Inertia::defer(fn () => $this->getAssetIndicators($asset)),
            'priceHistory' => Inertia::defer(fn () => $this->getPriceHistory($asset, $chartPeriod)),
            'predictionChartData' => Inertia::defer(fn () => $this->getPredictionChartData($asset)),
            'predictionHistory' => Inertia::defer(fn () => $this->getPredictionHistory($asset)),
        ]);
    }

    private function getAssetPredictions(Asset $asset): array
    {
        $currentPrice = $asset->cachedPrice?->price ?? $asset->latestPrice?->last ?? 0;

        return PredictedAssetPrice::where('pid', $asset->inv_id)
            ->orderByDesc('timestamp')
            ->get()
            ->groupBy('horizon')
            ->map(function ($group) use ($currentPrice) {
                $p = $group->first();
                $expectedGain = $currentPrice > 0
                    ? (($p->price_prediction - $currentPrice) / $currentPrice) * 100
                    : 0;

                // timestamp is in seconds, not milliseconds
                $timestamp = $p->timestamp ? Carbon::createFromTimestamp($p->timestamp) : null;
                $horizonMinutes = Horizon::minutes($p->horizon);
                $targetTimestamp = $timestamp && $horizonMinutes > 0
                    ? $timestamp->copy()->addMinutes($horizonMinutes)->toISOString()
                    : null;

                return [
                    'horizon' => $p->horizon,
                    'horizonLabel' => Horizon::label($p->horizon),
                    'predictedPrice' => $p->price_prediction,
                    'confidence' => $p->confidence,
                    'expectedGainPercent' => round($expectedGain, 2),
                    'timestamp' => $timestamp?->toISOString(),
                    'targetTimestamp' => $targetTimestamp,
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
        // Database stores timestamps in seconds, not milliseconds
        $startTimestamp = now()->subDays($days)->timestamp;

        return AssetPrice::where('pid', $asset->inv_id)
            ->where('timestamp', '>=', $startTimestamp)
            ->orderBy('timestamp')
            ->get()
            ->map(fn ($price) => [
                // Convert to milliseconds for frontend JavaScript Date compatibility
                'timestamp' => $price->timestamp * 1000,
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
        // Get the latest prediction per horizon (unique horizons only)
        return PredictedAssetPrice::where('pid', $asset->inv_id)
            ->orderByDesc('timestamp')
            ->get()
            ->groupBy('horizon')
            ->map(function ($group) {
                $p = $group->first(); // Get the latest prediction for this horizon

                // timestamp is in seconds, not milliseconds
                $timestamp = $p->timestamp ? Carbon::createFromTimestamp($p->timestamp) : null;
                $horizonMinutes = Horizon::minutes($p->horizon);
                $targetTimestamp = $timestamp && $horizonMinutes > 0
                    ? $timestamp->copy()->addMinutes($horizonMinutes)->toISOString()
                    : null;

                return [
                    'predictedPrice' => $p->price_prediction,
                    'confidence' => $p->confidence,
                    'horizon' => $p->horizon,
                    'horizonLabel' => Horizon::label($p->horizon),
                    'timestamp' => $timestamp?->toISOString(),
                    'targetTimestamp' => $targetTimestamp,
                ];
            })
            ->sortBy('horizon')
            ->values()
            ->toArray();
    }

    /**
     * Get prediction chart data - projecting future prices from different horizons.
     *
     * @return array<int, array{timestamp: int, price: float, confidence: float, upperBound: float|null, lowerBound: float|null}>
     */
    private function getPredictionChartData(Asset $asset): array
    {
        $currentPrice = $asset->cachedPrice?->price ?? $asset->latestPrice?->last ?? 0;
        $lastTimestamp = $asset->cachedPrice?->price_time?->timestamp
            ?? now()->timestamp;

        // Get latest predictions grouped by horizon
        $predictions = PredictedAssetPrice::where('pid', $asset->inv_id)
            ->orderByDesc('timestamp')
            ->get()
            ->groupBy('horizon');

        $chartPoints = [];

        // Add current price as the start point (connection point)
        $chartPoints[] = [
            'timestamp' => $lastTimestamp * 1000, // Convert to milliseconds for frontend
            'price' => (float) $currentPrice,
            'confidence' => 100,
            'upperBound' => null,
            'lowerBound' => null,
            'isPrediction' => false,
        ];

        // Add prediction points for each horizon
        foreach ($predictions as $horizon => $group) {
            $p = $group->first();
            if (! $p) {
                continue;
            }

            $horizonMinutes = Horizon::minutes($horizon);
            if ($horizonMinutes <= 0) {
                continue;
            }

            // Calculate target timestamp from current time
            $targetTimestamp = ($lastTimestamp + ($horizonMinutes * 60)) * 1000;

            // Calculate confidence bands based on confidence level
            // Higher confidence = narrower band
            $bandPercent = max(0.5, (100 - $p->confidence) / 10); // 0.5% to 10% band
            $upperBound = $p->upper_bound ?? $p->price_prediction * (1 + $bandPercent / 100);
            $lowerBound = $p->lower_bound ?? $p->price_prediction * (1 - $bandPercent / 100);

            $chartPoints[] = [
                'timestamp' => (int) $targetTimestamp,
                'price' => (float) $p->price_prediction,
                'confidence' => (float) $p->confidence,
                'upperBound' => (float) $upperBound,
                'lowerBound' => (float) $lowerBound,
                'isPrediction' => true,
                'horizon' => $horizon,
                'horizonLabel' => Horizon::label($horizon),
            ];
        }

        // Sort by timestamp
        usort($chartPoints, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return $chartPoints;
    }
}
