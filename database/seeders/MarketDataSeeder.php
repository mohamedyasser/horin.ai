<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\InstantAnomaly;
use App\Models\InstantRecommendation;
use App\Models\InstantSignalConsumer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketDataSeeder extends Seeder
{
    /**
     * Seed asset prices, indicators, signals, patterns, anomalies, and recommendations
     * for ALL stock and crypto assets.
     */
    public function run(): void
    {
        $assets = Asset::whereNotNull('inv_id')->get();

        if ($assets->isEmpty()) {
            $this->command->warn('No assets found. Run country seeders first.');

            return;
        }

        $stockAssets = $assets->where('type', 'stock');
        $allAssets = $assets; // stocks + crypto + index

        $this->command->info("Found {$stockAssets->count()} stocks, {$allAssets->count()} total assets.");

        $this->seedAssetPrices($stockAssets);
        $this->seedPredictions($stockAssets);
        $this->seedIndicators($stockAssets);
        $this->seedRecommendations($stockAssets);
        $this->seedDetectedSignals($stockAssets);
        $this->seedSignalClassifications();
        $this->seedSignalAnomalies();
        $this->seedSignalConsumers();
        $this->seedPatternDetections($stockAssets);
        $this->seedAnomalies($stockAssets);
    }

    /**
     * Seed historical price data for ALL stock assets.
     */
    private function seedAssetPrices(mixed $assets): void
    {
        $rows = [];
        $now = now();

        foreach ($assets as $asset) {
            $basePrice = fake()->randomFloat(2, 5, 500);

            // 7 days of daily price data per asset
            for ($day = 6; $day >= 0; $day--) {
                $timestamp = $now->copy()->subDays($day)->startOfDay()->timestamp;
                $change = $basePrice * fake()->randomFloat(4, -0.05, 0.05);
                $open = $basePrice + $change;
                $high = $open * fake()->randomFloat(4, 1.001, 1.04);
                $low = $open * fake()->randomFloat(4, 0.96, 0.999);
                $close = fake()->randomFloat(2, $low, $high);

                $rows[] = [
                    'pid' => $asset->inv_id,
                    'timestamp' => $timestamp,
                    'last' => round($close, 4),
                    'last_numeric' => round($close, 4),
                    'high' => round($high, 4),
                    'low' => round($low, 4),
                    'last_close' => round($open, 4),
                    'pc' => round($close - $open, 4),
                    'pcp' => round((($close - $open) / max($open, 0.01)) * 100, 2),
                    'turnover_numeric' => fake()->numberBetween(50000, 5000000),
                ];

                $basePrice = $close;
            }

            // Bulk insert every 100 assets to manage memory
            if (count($rows) >= 700) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('asset_prices')->insertOrIgnore($chunk);
                }
                $rows = [];
            }
        }

        // Insert remaining
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('asset_prices')->insertOrIgnore($chunk);
        }

        $this->command->info("Seeded price data for {$assets->count()} assets.");
    }

    /**
     * Seed AI price predictions for ALL stock assets.
     */
    private function seedPredictions(mixed $assets): void
    {
        $rows = [];
        $now = now();

        $models = ['lstm_v3', 'transformer_v2', 'ensemble_v1'];
        $horizons = ['1hour' => 60, '4hour' => 240, '1day' => 1440, '1week' => 10080];

        foreach ($assets as $asset) {
            $basePrice = fake()->randomFloat(2, 10, 300);

            // Pick 1 random model per asset (to keep data volume reasonable)
            $model = fake()->randomElement($models);

            foreach ($horizons as $horizon => $minutes) {
                $predicted = $basePrice * fake()->randomFloat(4, 0.95, 1.08);
                $confidence = fake()->randomFloat(4, 0.55, 0.95);
                $spread = $basePrice * (1 - $confidence) * 0.2;
                $ts = $now->timestamp;

                $rows[] = [
                    'pid' => $asset->inv_id,
                    'symbol' => $asset->symbol,
                    'model_name' => $model,
                    'module' => 'price_prediction',
                    'horizon' => $horizon,
                    'horizon_minutes' => $minutes,
                    'timestamp' => $ts,
                    'target_timestamp' => $ts + ($minutes * 60),
                    'prediction_time' => $now->toDateTimeString(),
                    'price_prediction' => round($predicted, 4),
                    'confidence' => round($confidence, 4),
                    'lower_bound' => round($predicted - $spread, 4),
                    'upper_bound' => round($predicted + $spread, 4),
                    'created_at' => $now->toDateTimeString(),
                ];
            }

            if (count($rows) >= 500) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('predicted_asset_prices')->insertOrIgnore($chunk);
                }
                $rows = [];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('predicted_asset_prices')->insertOrIgnore($chunk);
        }

        $this->command->info("Seeded predictions for {$assets->count()} assets.");
    }

    /**
     * Seed technical indicators for ALL stock assets.
     */
    private function seedIndicators(mixed $assets): void
    {
        $rows = [];
        $now = now();

        foreach ($assets as $asset) {
            $price = fake()->randomFloat(2, 10, 300);
            $timestamp = $now->copy()->startOfDay()->timestamp;

            // 1 latest indicator snapshot per asset
            $rows[] = [
                'pid' => $asset->inv_id,
                'timestamp' => $timestamp,
                'rsi' => fake()->randomFloat(2, 15, 85),
                'macd_line' => fake()->randomFloat(4, -5, 5),
                'macd_signal' => fake()->randomFloat(4, -4, 4),
                'macd_histogram' => fake()->randomFloat(4, -2, 2),
                'bb_middle' => round($price, 2),
                'bb_upper' => round($price * 1.02, 2),
                'bb_lower' => round($price * 0.98, 2),
                'ema' => round($price * fake()->randomFloat(4, 0.98, 1.02), 2),
                'sma' => round($price * fake()->randomFloat(4, 0.97, 1.03), 2),
                'adx' => fake()->randomFloat(2, 10, 60),
                'stoch_k' => fake()->randomFloat(2, 5, 95),
                'stoch_d' => fake()->randomFloat(2, 5, 95),
                'cci' => fake()->randomFloat(2, -200, 200),
                'williams_r' => fake()->randomFloat(2, -100, 0),
                'roc' => fake()->randomFloat(2, -10, 10),
                'momentum' => fake()->randomFloat(2, -20, 20),
                'atr' => fake()->randomFloat(4, 0.5, 15),
                'obv' => fake()->numberBetween(-5000000, 5000000),
                'volume_ma' => fake()->numberBetween(100000, 3000000),
                'vwap' => round($price * fake()->randomFloat(4, 0.99, 1.01), 2),
                'supertrend' => round($price * fake()->randomFloat(4, 0.96, 1.04), 2),
                'psar' => round($price * fake()->randomFloat(4, 0.95, 1.05), 2),
            ];

            if (count($rows) >= 500) {
                DB::table('instant_indicators')->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if (! empty($rows)) {
            DB::table('instant_indicators')->insertOrIgnore($rows);
        }

        $this->command->info("Seeded indicators for {$assets->count()} assets.");
    }

    /**
     * Seed trading recommendations for ALL stock assets.
     */
    private function seedRecommendations(mixed $assets): void
    {
        $now = now();
        $recommendations = ['strong_buy', 'buy', 'hold', 'sell', 'strong_sell'];

        foreach ($assets as $asset) {
            InstantRecommendation::firstOrCreate(
                ['pid' => $asset->inv_id],
                [
                    'score' => fake()->randomFloat(2, 1, 5),
                    'recommendation' => fake()->randomElement($recommendations),
                    'created_at' => $now->copy()->subHours(rand(1, 48)),
                ]
            );
        }

        $this->command->info("Seeded recommendations for {$assets->count()} assets.");
    }

    /**
     * Seed detected trading signals for ALL stock assets.
     */
    private function seedDetectedSignals(mixed $assets): void
    {
        $rows = [];
        $now = now();

        $indicators = ['RSI', 'MACD', 'BB', 'EMA', 'SMA', 'ADX', 'STOCH', 'CCI'];
        $signalTypes = ['oversold', 'overbought', 'bullish_cross', 'bearish_cross', 'breakout', 'breakdown', 'divergence'];

        foreach ($assets as $asset) {
            // 1-2 signals per asset
            $signalCount = rand(1, 2);

            for ($i = 0; $i < $signalCount; $i++) {
                $rows[] = [
                    'id' => Str::uuid()->toString(),
                    'pid' => $asset->inv_id,
                    'timestamp' => $now->copy()->subHours(rand(1, 72))->timestamp,
                    'indicator' => fake()->randomElement($indicators),
                    'signal_type' => fake()->randomElement($signalTypes),
                    'value' => json_encode([
                        'current' => fake()->randomFloat(2, 10, 300),
                        'threshold' => fake()->randomFloat(2, 20, 80),
                    ]),
                    'strength' => fake()->randomFloat(4, 0.3, 1.0),
                    'created_at' => $now->copy()->subHours(rand(1, 72))->toDateTimeString(),
                ];
            }

            if (count($rows) >= 500) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('instant_detected_signals')->insertOrIgnore($chunk);
                }
                $rows = [];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('instant_detected_signals')->insertOrIgnore($chunk);
        }

        $this->command->info("Seeded signals for {$assets->count()} assets.");
    }

    /**
     * Seed chart pattern detections for ALL stock assets.
     */
    private function seedPatternDetections(mixed $assets): void
    {
        $rows = [];
        $now = now();

        $patternTypes = ['head_shoulder', 'double_top', 'double_bottom', 'triangle', 'wedge', 'channel', 'trendline', 'support_resistance'];

        foreach ($assets as $asset) {
            $timestamp = $now->copy()->startOfDay()->timestamp;
            $detectedPatterns = fake()->randomElements($patternTypes, rand(0, 3));

            $patternsJson = [];
            foreach ($detectedPatterns as $pattern) {
                $patternsJson[] = [
                    'type' => $pattern,
                    'confidence' => fake()->randomFloat(2, 0.5, 0.99),
                    'direction' => fake()->randomElement(['bullish', 'bearish', 'neutral']),
                    'target_price' => fake()->randomFloat(2, 10, 500),
                ];
            }

            $rows[] = [
                'pid' => $asset->inv_id,
                'timestamp' => $timestamp,
                'patterns' => json_encode($patternsJson),
                'has_head_shoulder' => in_array('head_shoulder', $detectedPatterns),
                'has_multiple_tops_bottoms' => false,
                'has_triangle' => in_array('triangle', $detectedPatterns),
                'has_wedge' => in_array('wedge', $detectedPatterns),
                'has_channel' => in_array('channel', $detectedPatterns),
                'has_double_top_bottom' => in_array('double_top', $detectedPatterns) || in_array('double_bottom', $detectedPatterns),
                'has_trendline' => in_array('trendline', $detectedPatterns),
                'has_support_resistance' => in_array('support_resistance', $detectedPatterns),
                'has_pivots' => fake()->boolean(30),
                'pattern_count' => count($detectedPatterns),
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ];

            if (count($rows) >= 500) {
                DB::table('instant_pattern_detections')->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if (! empty($rows)) {
            DB::table('instant_pattern_detections')->insertOrIgnore($rows);
        }

        $this->command->info("Seeded pattern detections for {$assets->count()} assets.");
    }

    /**
     * Seed anomaly detections for ALL stock assets (randomly ~30% of them).
     */
    private function seedAnomalies(mixed $assets): void
    {
        $now = now();
        $anomalyTypes = ['price_spike', 'volume_surge', 'volatility_explosion', 'price_gap', 'unusual_volume'];
        $windows = ['5m', '15m', '1h', '4h', '1d'];
        $count = 0;

        foreach ($assets as $asset) {
            // 30% chance per asset
            if (fake()->boolean(30)) {
                InstantAnomaly::create([
                    'symbol' => $asset->symbol,
                    'anomaly_type' => fake()->randomElement($anomalyTypes),
                    'confidence_score' => fake()->randomFloat(2, 0.6, 0.99),
                    'detected_at' => $now->copy()->subHours(rand(1, 168)),
                    'window' => fake()->randomElement($windows),
                    'price' => fake()->randomFloat(8, 5, 500),
                    'volume' => fake()->numberBetween(100000, 10000000),
                    'extra' => [
                        'expected_value' => fake()->randomFloat(2, 5, 400),
                        'deviation' => fake()->randomFloat(2, 1.5, 5.0),
                        'z_score' => fake()->randomFloat(2, 2.0, 6.0),
                    ],
                ]);
                $count++;
            }
        }

        $this->command->info("Seeded {$count} anomaly records.");
    }

    /**
     * Seed signal classifications based on existing detected signals.
     */
    private function seedSignalClassifications(): void
    {
        $signals = DB::table('instant_detected_signals')->get();

        if ($signals->isEmpty()) {
            return;
        }

        $classifications = ['strong_signal', 'weak_signal', 'noise', 'confirmed', 'unconfirmed', 'reversal', 'continuation'];
        $rows = [];

        foreach ($signals as $signal) {
            if (fake()->boolean(60)) {
                $rows[] = [
                    'id' => Str::uuid()->toString(),
                    'pid' => $signal->pid,
                    'signal_id' => $signal->id,
                    'classification' => fake()->randomElement($classifications),
                    'confidence' => fake()->randomFloat(4, 0.4, 0.99),
                    'metadata' => json_encode([
                        'model' => fake()->randomElement(['ml_classifier_v2', 'rule_based_v1', 'ensemble']),
                        'features_used' => rand(5, 20),
                    ]),
                    'created_at' => $signal->created_at,
                ];
            }

            if (count($rows) >= 500) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('instant_signal_classifications')->insertOrIgnore($chunk);
                }
                $rows = [];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('instant_signal_classifications')->insertOrIgnore($chunk);
        }

        $this->command->info('Seeded signal classifications.');
    }

    /**
     * Seed signal anomalies based on existing detected signals.
     */
    private function seedSignalAnomalies(): void
    {
        $signals = DB::table('instant_detected_signals')->get();

        if ($signals->isEmpty()) {
            return;
        }

        $rows = [];

        foreach ($signals as $signal) {
            if (fake()->boolean(20)) {
                $rows[] = [
                    'id' => Str::uuid()->toString(),
                    'signal_id' => $signal->id,
                    'pid' => $signal->pid,
                    'indicator' => $signal->indicator,
                    'signal_type' => $signal->signal_type,
                    'anomaly_score' => fake()->randomFloat(4, 0.5, 1.0),
                    'created_at' => $signal->created_at,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('instant_signal_anomalies')->insertOrIgnore($chunk);
        }

        $this->command->info('Seeded signal anomalies.');
    }

    /**
     * Seed signal consumers and their actions.
     */
    private function seedSignalConsumers(): void
    {
        $consumers = [
            [
                'name' => 'Alert Engine',
                'description' => 'Processes signals to trigger user alerts',
                'is_active' => true,
                'config' => ['min_strength' => 0.5, 'cooldown_seconds' => 300],
            ],
            [
                'name' => 'Portfolio Analyzer',
                'description' => 'Evaluates signals against user portfolios',
                'is_active' => true,
                'config' => ['check_holdings' => true, 'check_watchlist' => true],
            ],
            [
                'name' => 'Recommendation Engine',
                'description' => 'Uses signals to update asset recommendations',
                'is_active' => true,
                'config' => ['weight' => 0.3, 'decay_hours' => 24],
            ],
            [
                'name' => 'News Correlator',
                'description' => 'Correlates signals with news events',
                'is_active' => false,
                'config' => ['lookback_hours' => 6, 'min_relevance' => 0.6],
            ],
        ];

        foreach ($consumers as $consumerData) {
            $consumer = InstantSignalConsumer::firstOrCreate(
                ['name' => $consumerData['name']],
                $consumerData,
            );

            $classifications = DB::table('instant_signal_classifications')->inRandomOrder()->limit(5)->get();
            $actionTypes = ['alert_triggered', 'recommendation_updated', 'portfolio_rebalance_suggested', 'notification_sent', 'logged'];

            foreach ($classifications as $classification) {
                DB::table('instant_consumer_actions')->insertOrIgnore([
                    'id' => Str::uuid()->toString(),
                    'consumer_id' => $consumer->id,
                    'classification_id' => $classification->id,
                    'action_type' => fake()->randomElement($actionTypes),
                    'action_data' => json_encode([
                        'result' => fake()->randomElement(['success', 'skipped', 'queued']),
                        'processed_at' => now()->subMinutes(rand(1, 120))->toDateTimeString(),
                    ]),
                    'status' => fake()->randomElement(['completed', 'pending', 'failed']),
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ]);
            }
        }

        $this->command->info('Seeded signal consumers and consumer actions.');
    }
}
