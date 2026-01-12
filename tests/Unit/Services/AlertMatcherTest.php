<?php

namespace Tests\Unit\Services;

use App\Models\Alert;
use App\Models\LatestAssetPrice;
use App\Services\AlertMatcher;
use App\Services\AlertScopeResolver;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AlertMatcherTest extends TestCase
{
    private AlertMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $scopeResolver = Mockery::mock(AlertScopeResolver::class);
        $scopeResolver->shouldReceive('getEntryPrice')->andReturn(50.0);

        $this->matcher = new AlertMatcher($scopeResolver);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createMockAlert(array $attributes = []): Alert
    {
        $alert = Mockery::mock(Alert::class)->makePartial();
        $alert->shouldReceive('getAttribute')->with('id')->andReturn($attributes['id'] ?? 'test-alert-id');
        $alert->shouldReceive('getAttribute')->with('user_id')->andReturn($attributes['user_id'] ?? 'test-user-id');
        $alert->shouldReceive('getAttribute')->with('asset_id')->andReturn($attributes['asset_id'] ?? 'test-asset-id');
        $alert->shouldReceive('getAttribute')->with('type')->andReturn($attributes['type'] ?? 'price');
        $alert->shouldReceive('getAttribute')->with('trigger_type')->andReturn($attributes['trigger_type'] ?? 'target_price');
        $alert->shouldReceive('getAttribute')->with('direction')->andReturn($attributes['direction'] ?? 'above');
        $alert->shouldReceive('getAttribute')->with('parameters')->andReturn($attributes['parameters'] ?? []);

        // Allow direct property access
        $alert->id = $attributes['id'] ?? 'test-alert-id';
        $alert->trigger_type = $attributes['trigger_type'] ?? 'target_price';
        $alert->direction = $attributes['direction'] ?? 'above';
        $alert->parameters = $attributes['parameters'] ?? [];

        return $alert;
    }

    private function createMockPrice(array $attributes = []): LatestAssetPrice
    {
        $price = new LatestAssetPrice;
        $price->pid = $attributes['pid'] ?? 'test-pid';
        $price->price = $attributes['price'] ?? 100.0;
        $price->change_percent = $attributes['change_percent'] ?? 0.0;
        $price->open = $attributes['open'] ?? 100.0;
        $price->prev_close = $attributes['prev_close'] ?? 100.0;
        $price->volume = $attributes['volume'] ?? 10000;
        $price->high_52w = $attributes['high_52w'] ?? null;
        $price->low_52w = $attributes['low_52w'] ?? null;

        return $price;
    }

    // ==================== Target Price Tests ====================

    public function test_target_price_triggers_when_price_above_target(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'target_price',
            'direction' => 'above',
            'parameters' => ['target_price' => 100.0],
        ]);

        $price = $this->createMockPrice(['price' => 105.0, 'change_percent' => 5.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
        $this->assertEquals(105.0, $result->triggerValue);
        $this->assertEquals('above', $result->context['direction']);
    }

    public function test_target_price_does_not_trigger_when_price_below_target(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'target_price',
            'direction' => 'above',
            'parameters' => ['target_price' => 100.0],
        ]);

        $price = $this->createMockPrice(['price' => 95.0, 'change_percent' => -5.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertFalse($result->triggered);
    }

    public function test_target_price_triggers_when_price_below_target_with_below_direction(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'target_price',
            'direction' => 'below',
            'parameters' => ['target_price' => 100.0],
        ]);

        $price = $this->createMockPrice(['price' => 95.0, 'change_percent' => -5.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
    }

    public function test_target_price_triggers_with_both_direction(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'target_price',
            'direction' => 'both',
            'parameters' => ['target_price' => 100.0, 'direction' => 'both'],
        ]);

        $price = $this->createMockPrice(['price' => 105.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
    }

    // ==================== Breakout Tests ====================

    public function test_breakout_triggers_when_price_breaks_above_level(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'breakout',
            'parameters' => ['level' => 100.0, 'direction' => 'above'],
        ]);

        $price = $this->createMockPrice(['price' => 101.0, 'volume' => 10000]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
        $this->assertEquals(101.0, $result->triggerValue);
    }

    public function test_breakout_does_not_trigger_when_price_at_level(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'breakout',
            'parameters' => ['level' => 100.0, 'direction' => 'above'],
        ]);

        $price = $this->createMockPrice(['price' => 100.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertFalse($result->triggered);
    }

    public function test_breakout_triggers_when_price_breaks_below_level(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'breakout',
            'parameters' => ['level' => 100.0, 'direction' => 'below'],
        ]);

        $price = $this->createMockPrice(['price' => 99.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
    }

    // ==================== Zone Tests ====================

    public function test_zone_triggers_on_enter(): void
    {
        $alert = $this->createMockAlert([
            'id' => 'zone-test-1',
            'trigger_type' => 'zone',
            'parameters' => [
                'zone_low' => 95.0,
                'zone_high' => 105.0,
                'trigger_on' => 'enter',
            ],
        ]);

        Cache::forget('alert:zone-test-1:zone_state');

        $price = $this->createMockPrice(['price' => 100.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
        $this->assertTrue($result->context['is_in_zone']);
    }

    public function test_zone_triggers_on_exit(): void
    {
        $alert = $this->createMockAlert([
            'id' => 'zone-test-2',
            'trigger_type' => 'zone',
            'parameters' => [
                'zone_low' => 95.0,
                'zone_high' => 105.0,
                'trigger_on' => 'exit',
            ],
        ]);

        Cache::put('alert:zone-test-2:zone_state', true, 86400);

        $price = $this->createMockPrice(['price' => 110.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
        $this->assertFalse($result->context['is_in_zone']);
        $this->assertTrue($result->context['was_in_zone']);
    }

    public function test_zone_does_not_trigger_when_staying_in_zone(): void
    {
        $alert = $this->createMockAlert([
            'id' => 'zone-test-3',
            'trigger_type' => 'zone',
            'parameters' => [
                'zone_low' => 95.0,
                'zone_high' => 105.0,
                'trigger_on' => 'enter',
            ],
        ]);

        Cache::put('alert:zone-test-3:zone_state', true, 86400);

        $price = $this->createMockPrice(['price' => 100.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertFalse($result->triggered);
    }

    // ==================== Daily Change Tests ====================

    public function test_daily_change_triggers_when_threshold_exceeded(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'daily_change',
            'parameters' => [
                'threshold_percent' => 5.0,
                'direction' => 'both',
            ],
        ]);

        $price = $this->createMockPrice([
            'price' => 110.0,
            'change_percent' => 6.5,
            'open' => 103.29,
        ]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
        $this->assertEquals(6.5, $result->triggerValue);
    }

    public function test_daily_change_respects_up_direction_filter(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'daily_change',
            'parameters' => [
                'threshold_percent' => 5.0,
                'direction' => 'up',
            ],
        ]);

        $price = $this->createMockPrice([
            'price' => 90.0,
            'change_percent' => -6.5,
        ]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertFalse($result->triggered);
    }

    public function test_daily_change_respects_down_direction_filter(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'daily_change',
            'parameters' => [
                'threshold_percent' => 5.0,
                'direction' => 'down',
            ],
        ]);

        $price = $this->createMockPrice([
            'price' => 90.0,
            'change_percent' => -6.5,
        ]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
    }

    // ==================== Gap Tests ====================

    public function test_gap_triggers_when_gap_up_exceeds_threshold(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'gap',
            'parameters' => [
                'gap_threshold_percent' => 3.0,
                'direction' => 'up',
            ],
        ]);

        $price = $this->createMockPrice([
            'price' => 105.0,
            'open' => 104.0,
            'prev_close' => 100.0,
        ]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
        $this->assertEquals(4.0, $result->triggerValue);
    }

    public function test_gap_does_not_trigger_when_direction_mismatch(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'gap',
            'parameters' => [
                'gap_threshold_percent' => 3.0,
                'direction' => 'up',
            ],
        ]);

        $price = $this->createMockPrice([
            'price' => 95.0,
            'open' => 96.0,
            'prev_close' => 100.0,
        ]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertFalse($result->triggered);
    }

    public function test_gap_triggers_both_directions(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'gap',
            'parameters' => [
                'gap_threshold_percent' => 3.0,
                'direction' => 'both',
            ],
        ]);

        $price = $this->createMockPrice([
            'price' => 95.0,
            'open' => 96.0,
            'prev_close' => 100.0,
        ]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
    }

    // ==================== 52 Week Tests ====================

    public function test_52week_triggers_on_new_high(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => '52week',
            'parameters' => ['type' => 'high'],
        ]);

        $price = $this->createMockPrice([
            'price' => 155.0,
            'high_52w' => 150.0,
        ]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
    }

    public function test_52week_triggers_on_new_low(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => '52week',
            'parameters' => ['type' => 'low'],
        ]);

        $price = $this->createMockPrice([
            'price' => 45.0,
            'low_52w' => 50.0,
        ]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
    }

    public function test_52week_does_not_trigger_when_not_at_extreme(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => '52week',
            'parameters' => ['type' => 'high'],
        ]);

        $price = $this->createMockPrice([
            'price' => 140.0,
            'high_52w' => 150.0,
        ]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertFalse($result->triggered);
    }

    // ==================== Signal Tests ====================

    public function test_signal_triggers_when_criteria_met(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'signal',
            'trigger_type' => 'signal',
            'parameters' => [
                'indicators' => ['RSI', 'MACD'],
                'signal_types' => ['oversold', 'bullish_crossover'],
                'min_strength' => 0.7,
            ],
        ]);

        $signalData = [
            'indicator' => 'RSI',
            'signal_type' => 'oversold',
            'strength' => 0.85,
            'confidence' => 0.9,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
        $this->assertEquals(0.85, $result->triggerValue);
    }

    public function test_signal_does_not_trigger_when_strength_too_low(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'signal',
            'trigger_type' => 'signal',
            'parameters' => [
                'indicators' => ['RSI'],
                'min_strength' => 0.8,
            ],
        ]);

        $signalData = [
            'indicator' => 'RSI',
            'signal_type' => 'oversold',
            'strength' => 0.6,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertFalse($result->triggered);
    }

    public function test_signal_triggers_with_empty_indicator_filter(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'signal',
            'trigger_type' => 'signal',
            'parameters' => [
                'indicators' => [],
                'min_strength' => 0.7,
            ],
        ]);

        $signalData = [
            'indicator' => 'ANY_INDICATOR',
            'signal_type' => 'any_type',
            'strength' => 0.85,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
    }

    // ==================== Prediction Tests ====================

    public function test_prediction_triggers_when_direction_and_confidence_match(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'prediction',
            'trigger_type' => 'prediction',
            'parameters' => [
                'direction' => 'up',
                'min_confidence' => 0.75,
            ],
        ]);

        $signalData = [
            'direction' => 'up',
            'confidence' => 0.85,
            'horizon' => '1day',
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
        $this->assertEquals(0.85, $result->triggerValue);
    }

    public function test_prediction_does_not_trigger_when_direction_mismatch(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'prediction',
            'trigger_type' => 'prediction',
            'parameters' => [
                'direction' => 'up',
                'min_confidence' => 0.75,
            ],
        ]);

        $signalData = [
            'direction' => 'down',
            'confidence' => 0.95,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertFalse($result->triggered);
    }

    public function test_prediction_with_both_direction_triggers_either_way(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'prediction',
            'trigger_type' => 'prediction',
            'parameters' => [
                'direction' => 'both',
                'min_confidence' => 0.75,
            ],
        ]);

        $signalData = [
            'direction' => 'down',
            'confidence' => 0.85,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
    }

    // ==================== Anomaly Tests ====================

    public function test_anomaly_triggers_when_score_and_type_match(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'anomaly',
            'trigger_type' => 'anomaly',
            'parameters' => [
                'anomaly_types' => ['price_spike', 'volume_surge'],
                'min_confidence' => 0.8,
                'severity' => ['high', 'critical'],
            ],
        ]);

        $signalData = [
            'types' => ['price_spike'],
            'score' => 0.92,
            'reasons' => ['Unusual price movement detected'],
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
        $this->assertEquals('critical', $result->context['severity']);
    }

    public function test_anomaly_does_not_trigger_when_score_too_low(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'anomaly',
            'trigger_type' => 'anomaly',
            'parameters' => [
                'min_confidence' => 0.8,
                'severity' => ['high', 'critical'],
            ],
        ]);

        $signalData = [
            'types' => ['price_spike'],
            'score' => 0.6,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertFalse($result->triggered);
    }

    // ==================== Pattern Tests ====================

    public function test_pattern_triggers_when_pattern_detected(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'pattern',
            'trigger_type' => 'pattern',
            'parameters' => [
                'patterns' => ['double_bottom', 'head_and_shoulders'],
                'min_confidence' => 0.7,
                'pattern_status' => 'confirmed',
            ],
        ]);

        $signalData = [
            'patterns' => [
                [
                    'type' => 'double_bottom',
                    'confidence' => 0.85,
                    'metadata' => ['breakout_confirmed' => true],
                ],
            ],
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
        $this->assertEquals('double_bottom', $result->context['pattern_type']);
    }

    public function test_pattern_does_not_trigger_when_no_matching_pattern(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'pattern',
            'trigger_type' => 'pattern',
            'parameters' => [
                'patterns' => ['double_bottom'],
                'min_confidence' => 0.7,
            ],
        ]);

        $signalData = [
            'patterns' => [
                [
                    'type' => 'head_and_shoulders',
                    'confidence' => 0.85,
                ],
            ],
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertFalse($result->triggered);
    }

    // ==================== Compound Alert Tests ====================

    public function test_compound_and_logic_requires_all_conditions(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'signal',
            'trigger_type' => 'compound_intelligence',
            'parameters' => [
                'logic' => 'and',
                'conditions' => [
                    [
                        'type' => 'signal',
                        'parameters' => ['indicators' => ['RSI'], 'min_strength' => 0.7],
                    ],
                    [
                        'type' => 'prediction',
                        'parameters' => ['direction' => 'up', 'min_confidence' => 0.75],
                    ],
                ],
            ],
        ]);

        $signalData = [
            'indicator' => 'RSI',
            'signal_type' => 'oversold',
            'strength' => 0.8,
            'direction' => 'up',
            'confidence' => 0.85,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
        $this->assertEquals('and', $result->context['logic']);
        $this->assertEquals(2, $result->context['conditions_count']);
        $this->assertEquals(2, $result->context['triggered_count']);
    }

    public function test_compound_and_logic_fails_when_one_condition_fails(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'signal',
            'trigger_type' => 'compound_intelligence',
            'parameters' => [
                'logic' => 'and',
                'conditions' => [
                    [
                        'type' => 'signal',
                        'parameters' => ['indicators' => ['RSI'], 'min_strength' => 0.7],
                    ],
                    [
                        'type' => 'prediction',
                        'parameters' => ['direction' => 'up', 'min_confidence' => 0.9],
                    ],
                ],
            ],
        ]);

        $signalData = [
            'indicator' => 'RSI',
            'signal_type' => 'oversold',
            'strength' => 0.8,
            'direction' => 'up',
            'confidence' => 0.7,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertFalse($result->triggered);
        $this->assertEquals(1, $result->context['triggered_count']);
    }

    public function test_compound_or_logic_triggers_when_any_condition_met(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'signal',
            'trigger_type' => 'compound_intelligence',
            'parameters' => [
                'logic' => 'or',
                'conditions' => [
                    [
                        'type' => 'signal',
                        'parameters' => ['indicators' => ['RSI'], 'min_strength' => 0.9],
                    ],
                    [
                        'type' => 'prediction',
                        'parameters' => ['direction' => 'up', 'min_confidence' => 0.75],
                    ],
                ],
            ],
        ]);

        $signalData = [
            'indicator' => 'RSI',
            'signal_type' => 'oversold',
            'strength' => 0.5,
            'direction' => 'up',
            'confidence' => 0.85,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
        $this->assertEquals('or', $result->context['logic']);
        $this->assertEquals(1, $result->context['triggered_count']);
    }

    public function test_compound_with_empty_conditions_does_not_trigger(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'signal',
            'trigger_type' => 'compound_intelligence',
            'parameters' => [
                'logic' => 'and',
                'conditions' => [],
            ],
        ]);

        $result = $this->matcher->evaluateIntelligenceAlert($alert, []);

        $this->assertFalse($result->triggered);
    }

    // ==================== Price Alert From Signal Tests ====================

    public function test_price_alert_from_signal_target_price(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'target_price',
            'direction' => 'above',
            'parameters' => ['target_price' => 100.0],
        ]);

        $result = $this->matcher->evaluatePriceAlertFromSignal(
            $alert,
            105.0,
            100.0,
            ['changePercent' => 5.0]
        );

        $this->assertTrue($result->triggered);
        $this->assertEquals(105.0, $result->triggerValue);
    }

    public function test_price_alert_from_signal_zone_enter(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'zone',
            'parameters' => [
                'zone_low' => 95.0,
                'zone_high' => 105.0,
                'trigger_on' => 'enter',
            ],
        ]);

        $result = $this->matcher->evaluatePriceAlertFromSignal(
            $alert,
            100.0,
            90.0,
            []
        );

        $this->assertTrue($result->triggered);
        $this->assertTrue($result->context['is_in_zone']);
        $this->assertFalse($result->context['was_in_zone']);
    }

    public function test_price_alert_from_signal_daily_change(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'daily_change',
            'parameters' => [
                'threshold_percent' => 5.0,
                'direction' => 'both',
            ],
        ]);

        $result = $this->matcher->evaluatePriceAlertFromSignal(
            $alert,
            106.0,
            100.0,
            ['changePercent' => 6.0]
        );

        $this->assertTrue($result->triggered);
        $this->assertEquals(6.0, $result->triggerValue);
    }

    public function test_price_alert_from_signal_calculates_change_from_prices(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'daily_change',
            'parameters' => [
                'threshold_percent' => 5.0,
                'direction' => 'both',
            ],
        ]);

        $result = $this->matcher->evaluatePriceAlertFromSignal(
            $alert,
            110.0,
            100.0,
            []
        );

        $this->assertTrue($result->triggered);
        $this->assertEquals(10.0, $result->triggerValue);
    }

    // ==================== Recommendation Tests ====================

    public function test_recommendation_triggers_on_change(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'recommendation',
            'trigger_type' => 'recommendation',
            'parameters' => [
                'trigger_on' => 'change',
                'recommendations' => ['strong_buy', 'buy'],
                'min_score' => 0.75,
            ],
        ]);

        $signalData = [
            'action' => 'buy',
            'previous_action' => 'hold',
            'score' => 0.85,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
        $this->assertTrue($result->context['is_upgrade']);
    }

    public function test_recommendation_does_not_trigger_when_no_change(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'recommendation',
            'trigger_type' => 'recommendation',
            'parameters' => [
                'trigger_on' => 'change',
                'recommendations' => ['buy'],
                'min_score' => 0.75,
            ],
        ]);

        $signalData = [
            'action' => 'buy',
            'previous_action' => 'buy',
            'score' => 0.85,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertFalse($result->triggered);
    }

    public function test_recommendation_triggers_with_any_trigger_on(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'recommendation',
            'trigger_type' => 'recommendation',
            'parameters' => [
                'trigger_on' => 'any',
                'recommendations' => ['buy'],
                'min_score' => 0.75,
            ],
        ]);

        $signalData = [
            'action' => 'buy',
            'previous_action' => 'buy',
            'score' => 0.85,
        ];

        $result = $this->matcher->evaluateIntelligenceAlert($alert, $signalData);

        $this->assertTrue($result->triggered);
    }

    // ==================== Entry Return Tests ====================

    public function test_entry_return_triggers_when_near_entry_price(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'entry_return',
            'parameters' => [
                'entry_price' => 100.0,
                'tolerance_percent' => 1.0,
            ],
        ]);

        $price = $this->createMockPrice(['price' => 100.5]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertTrue($result->triggered);
        $this->assertLessThanOrEqual(1.0, $result->context['diff_percent']);
    }

    public function test_entry_return_does_not_trigger_when_far_from_entry(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'entry_return',
            'parameters' => [
                'entry_price' => 100.0,
                'tolerance_percent' => 1.0,
            ],
        ]);

        $price = $this->createMockPrice(['price' => 105.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertFalse($result->triggered);
    }

    // ==================== Unknown Trigger Type Tests ====================

    public function test_unknown_price_trigger_type_returns_no_trigger(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'unknown_type',
        ]);

        $price = $this->createMockPrice(['price' => 100.0]);

        $result = $this->matcher->evaluatePriceAlert($alert, $price);

        $this->assertFalse($result->triggered);
    }

    public function test_unknown_intelligence_trigger_type_returns_no_trigger(): void
    {
        $alert = $this->createMockAlert([
            'trigger_type' => 'unknown_type',
        ]);

        $result = $this->matcher->evaluateIntelligenceAlert($alert, []);

        $this->assertFalse($result->triggered);
    }
}
