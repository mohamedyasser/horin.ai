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
     * Seed asset prices, indicators, signals, patterns, anomalies, and recommendations.
     */
    public function run(): void
    {
        $assets = Asset::whereNotNull('inv_id')->get();

        if ($assets->isEmpty()) {
            $this->command->warn('No assets found. Run AssetSeeder first.');

            return;
        }

        $this->seedAssetPrices($assets);
        $this->seedPredictions($assets);
        $this->seedIndicators($assets);
        $this->seedDetectedSignals($assets);
        $this->seedSignalClassifications();
        $this->seedSignalAnomalies();
        $this->seedSignalConsumers();
        $this->seedPatternDetections($assets);
        $this->seedAnomalies($assets);
        $this->seedRecommendations($assets);
    }

    /**
     * Seed historical price data for assets.
     */
    private function seedAssetPrices(mixed $assets): void
    {
        $stockAssets = $assets->where('type', 'stock')->take(30);
        $rows = [];
        $now = now();

        foreach ($stockAssets as $asset) {
            $basePrice = fake()->randomFloat(2, 5, 500);

            // Generate 30 days of daily price data
            for ($day = 30; $day >= 0; $day--) {
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
                    'pcp' => round((($close - $open) / $open) * 100, 2),
                    'turnover_numeric' => fake()->numberBetween(50000, 5000000),
                ];

                $basePrice = $close;
            }
        }

        // Bulk insert in chunks
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('asset_prices')->insertOrIgnore($chunk);
        }

        $this->command->info('Seeded '.count($rows).' asset price records.');
    }

    /**
     * Seed AI price predictions for assets.
     */
    private function seedPredictions(mixed $assets): void
    {
        $stockAssets = $assets->where('type', 'stock')->take(20);
        $rows = [];
        $now = now();

        $models = ['lstm_v3', 'transformer_v2', 'ensemble_v1'];
        $horizons = ['1hour' => 60, '4hour' => 240, '1day' => 1440, '1week' => 10080];

        foreach ($stockAssets as $asset) {
            $basePrice = fake()->randomFloat(2, 10, 300);

            foreach ($models as $model) {
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
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('predicted_asset_prices')->insertOrIgnore($chunk);
        }

        $this->command->info('Seeded '.count($rows).' prediction records.');
    }

    /**
     * Seed technical indicators for assets.
     */
    private function seedIndicators(mixed $assets): void
    {
        $stockAssets = $assets->where('type', 'stock')->take(25);
        $rows = [];
        $now = now();

        foreach ($stockAssets as $asset) {
            $price = fake()->randomFloat(2, 10, 300);

            // 7 days of indicator data
            for ($day = 6; $day >= 0; $day--) {
                $timestamp = $now->copy()->subDays($day)->startOfDay()->timestamp;

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
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('instant_indicators')->insertOrIgnore($chunk);
        }

        $this->command->info('Seeded '.count($rows).' indicator records.');
    }

    /**
     * Seed detected trading signals.
     */
    private function seedDetectedSignals(mixed $assets): void
    {
        $stockAssets = $assets->where('type', 'stock')->take(20);
        $rows = [];
        $now = now();

        $indicators = ['RSI', 'MACD', 'BB', 'EMA', 'SMA', 'ADX', 'STOCH', 'CCI'];
        $signalTypes = ['oversold', 'overbought', 'bullish_cross', 'bearish_cross', 'breakout', 'breakdown', 'divergence'];

        foreach ($stockAssets as $asset) {
            $signalCount = rand(2, 6);

            for ($i = 0; $i < $signalCount; $i++) {
                $indicator = fake()->randomElement($indicators);
                $signalType = fake()->randomElement($signalTypes);

                $rows[] = [
                    'id' => Str::uuid()->toString(),
                    'pid' => $asset->inv_id,
                    'timestamp' => $now->copy()->subHours(rand(1, 72))->timestamp,
                    'indicator' => $indicator,
                    'signal_type' => $signalType,
                    'value' => json_encode([
                        'current' => fake()->randomFloat(2, 10, 300),
                        'threshold' => fake()->randomFloat(2, 20, 80),
                    ]),
                    'strength' => fake()->randomFloat(4, 0.3, 1.0),
                    'created_at' => $now->copy()->subHours(rand(1, 72))->toDateTimeString(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('instant_detected_signals')->insertOrIgnore($chunk);
        }

        $this->command->info('Seeded '.count($rows).' detected signal records.');
    }

    /**
     * Seed chart pattern detections.
     */
    private function seedPatternDetections(mixed $assets): void
    {
        $stockAssets = $assets->where('type', 'stock')->take(15);
        $rows = [];
        $now = now();

        $patternTypes = ['head_shoulder', 'double_top', 'double_bottom', 'triangle', 'wedge', 'channel', 'trendline', 'support_resistance'];

        foreach ($stockAssets as $asset) {
            for ($day = 4; $day >= 0; $day--) {
                $timestamp = $now->copy()->subDays($day)->startOfDay()->timestamp;
                $detectedPatterns = fake()->randomElements($patternTypes, rand(0, 4));

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
                    'created_at' => $now->copy()->subDays($day)->toDateTimeString(),
                    'updated_at' => $now->copy()->subDays($day)->toDateTimeString(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('instant_pattern_detections')->insertOrIgnore($chunk);
        }

        $this->command->info('Seeded '.count($rows).' pattern detection records.');
    }

    /**
     * Seed anomaly detections.
     */
    private function seedAnomalies(mixed $assets): void
    {
        $stockAssets = $assets->where('type', 'stock')->take(15);
        $now = now();

        $anomalyTypes = ['price_spike', 'volume_surge', 'volatility_explosion', 'price_gap', 'unusual_volume'];
        $windows = ['5m', '15m', '1h', '4h', '1d'];

        foreach ($stockAssets as $asset) {
            $count = rand(1, 4);

            for ($i = 0; $i < $count; $i++) {
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
            }
        }

        $this->command->info('Seeded anomaly records.');
    }

    /**
     * Seed trading recommendations.
     */
    private function seedRecommendations(mixed $assets): void
    {
        $stockAssets = $assets->where('type', 'stock')->take(25);
        $now = now();

        $recommendations = ['strong_buy', 'buy', 'hold', 'sell', 'strong_sell'];

        foreach ($stockAssets as $asset) {
            InstantRecommendation::create([
                'pid' => $asset->inv_id,
                'score' => fake()->randomFloat(2, 1, 5),
                'recommendation' => fake()->randomElement($recommendations),
                'created_at' => $now->copy()->subHours(rand(1, 48)),
            ]);
        }

        $this->command->info('Seeded recommendation records.');
    }

    /**
     * Seed signal classifications based on existing detected signals.
     */
    private function seedSignalClassifications(): void
    {
        $signals = DB::table('instant_detected_signals')->limit(50)->get();

        if ($signals->isEmpty()) {
            return;
        }

        $classifications = ['strong_signal', 'weak_signal', 'noise', 'confirmed', 'unconfirmed', 'reversal', 'continuation'];
        $rows = [];

        foreach ($signals as $signal) {
            // 70% chance of having a classification
            if (fake()->boolean(70)) {
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
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('instant_signal_classifications')->insertOrIgnore($chunk);
        }

        $this->command->info('Seeded '.count($rows).' signal classification records.');
    }

    /**
     * Seed signal anomalies based on existing detected signals.
     */
    private function seedSignalAnomalies(): void
    {
        $signals = DB::table('instant_detected_signals')->limit(30)->get();

        if ($signals->isEmpty()) {
            return;
        }

        $rows = [];

        foreach ($signals as $signal) {
            // 30% chance of a signal being anomalous
            if (fake()->boolean(30)) {
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

        $this->command->info('Seeded '.count($rows).' signal anomaly records.');
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

            // Seed consumer actions from existing classifications
            $classifications = DB::table('instant_signal_classifications')->limit(10)->get();
            $actionTypes = ['alert_triggered', 'recommendation_updated', 'portfolio_rebalance_suggested', 'notification_sent', 'logged'];

            foreach ($classifications->random(min(3, $classifications->count())) as $classification) {
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
