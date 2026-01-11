<?php

namespace Tests\Feature\Alerts;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use App\Services\Alerts\Evaluators\CompoundAlertEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompoundAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_compound_alert_triggers_when_all_and_conditions_met(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'type' => 'signal',
            'trigger_type' => 'compound_intelligence',
            'condition_logic' => 'and',
            'parameters' => [
                'conditions' => [
                    [
                        'id' => 'cond_1',
                        'type' => 'signal',
                        'indicators' => ['RSI'],
                        'signal_types' => ['oversold'],
                        'min_strength' => 0.7,
                    ],
                    [
                        'id' => 'cond_2',
                        'type' => 'prediction',
                        'direction' => 'up',
                        'min_confidence' => 0.7,
                    ],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, [
            'signals' => [
                ['indicator' => 'RSI', 'strength' => 0.8, 'signal_type' => 'oversold'],
            ],
            'predictions' => [
                ['direction' => 'up', 'confidence' => 0.85],
            ],
        ]);

        $this->assertTrue($result->triggered);
        $this->assertEquals('All conditions met', $result->reason);
    }

    public function test_compound_alert_does_not_trigger_when_and_condition_fails(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'condition_logic' => 'and',
            'parameters' => [
                'conditions' => [
                    [
                        'id' => 'cond_1',
                        'type' => 'signal',
                        'indicators' => ['RSI'],
                        'signal_types' => ['oversold'],
                        'min_strength' => 0.7,
                    ],
                    [
                        'id' => 'cond_2',
                        'type' => 'prediction',
                        'direction' => 'up',
                        'min_confidence' => 0.7,
                    ],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, [
            'signals' => [
                ['indicator' => 'RSI', 'strength' => 0.8, 'signal_type' => 'oversold'],
            ],
            'predictions' => [
                ['direction' => 'down', 'confidence' => 0.85], // Wrong direction
            ],
        ]);

        $this->assertFalse($result->triggered);
    }

    public function test_compound_alert_triggers_when_any_or_condition_met(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'type' => 'signal',
            'trigger_type' => 'compound_intelligence',
            'condition_logic' => 'or',
            'parameters' => [
                'conditions' => [
                    [
                        'id' => 'cond_1',
                        'type' => 'signal',
                        'indicators' => ['RSI'],
                        'signal_types' => ['oversold'],
                        'min_strength' => 0.7,
                    ],
                    [
                        'id' => 'cond_2',
                        'type' => 'prediction',
                        'direction' => 'up',
                        'min_confidence' => 0.9, // High threshold
                    ],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        // Only signal condition is met
        $result = $evaluator->evaluate($alert, [
            'signals' => [
                ['indicator' => 'RSI', 'strength' => 0.8, 'signal_type' => 'oversold'],
            ],
            'predictions' => [
                ['direction' => 'up', 'confidence' => 0.5], // Below threshold
            ],
        ]);

        $this->assertTrue($result->triggered);
        $this->assertEquals('One or more conditions met', $result->reason);
    }

    public function test_compound_alert_does_not_trigger_when_no_or_conditions_met(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'condition_logic' => 'or',
            'parameters' => [
                'conditions' => [
                    [
                        'id' => 'cond_1',
                        'type' => 'signal',
                        'indicators' => ['RSI'],
                        'signal_types' => ['oversold'],
                        'min_strength' => 0.9, // High threshold
                    ],
                    [
                        'id' => 'cond_2',
                        'type' => 'prediction',
                        'direction' => 'up',
                        'min_confidence' => 0.9, // High threshold
                    ],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, [
            'signals' => [
                ['indicator' => 'RSI', 'strength' => 0.5, 'signal_type' => 'oversold'], // Below threshold
            ],
            'predictions' => [
                ['direction' => 'up', 'confidence' => 0.5], // Below threshold
            ],
        ]);

        $this->assertFalse($result->triggered);
        $this->assertEquals('No conditions met', $result->reason);
    }

    public function test_compound_alert_handles_empty_conditions(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'condition_logic' => 'and',
            'parameters' => [
                'conditions' => [],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, [
            'signals' => [],
            'predictions' => [],
        ]);

        $this->assertFalse($result->triggered);
        $this->assertEquals('No conditions defined', $result->reason);
    }

    public function test_compound_alert_handles_unknown_condition_type(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'condition_logic' => 'and',
            'parameters' => [
                'conditions' => [
                    [
                        'id' => 'cond_1',
                        'type' => 'unknown_type',
                        'some_param' => 'value',
                    ],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, []);

        $this->assertFalse($result->triggered);
    }

    public function test_compound_alert_with_pattern_condition(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'condition_logic' => 'or',
            'parameters' => [
                'conditions' => [
                    [
                        'id' => 'cond_1',
                        'type' => 'pattern',
                        'patterns' => ['double_bottom'],
                        'pattern_status' => 'confirmed',
                        'min_confidence' => 0.7,
                    ],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, [
            'patterns' => [
                [
                    'pattern_type' => 'double_bottom',
                    'status' => 'confirmed',
                    'confidence' => 0.85,
                ],
            ],
        ]);

        $this->assertTrue($result->triggered);
    }

    public function test_compound_alert_with_anomaly_condition(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'condition_logic' => 'or',
            'parameters' => [
                'conditions' => [
                    [
                        'id' => 'cond_1',
                        'type' => 'anomaly',
                        'anomaly_types' => ['price_spike'],
                        'min_confidence' => 0.8,
                        'severity' => ['high', 'critical'],
                    ],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, [
            'anomalies' => [
                [
                    'type' => 'price_spike',
                    'severity' => 'high',
                    'confidence' => 0.9,
                ],
            ],
        ]);

        $this->assertTrue($result->triggered);
    }

    public function test_compound_alert_returns_context_with_conditions(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'condition_logic' => 'and',
            'parameters' => [
                'conditions' => [
                    [
                        'id' => 'cond_1',
                        'type' => 'signal',
                        'indicators' => ['RSI'],
                        'signal_types' => ['oversold'],
                        'min_strength' => 0.7,
                    ],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, [
            'signals' => [
                ['indicator' => 'RSI', 'strength' => 0.8, 'signal_type' => 'oversold'],
            ],
        ]);

        $this->assertTrue($result->triggered);
        $this->assertArrayHasKey('conditions', $result->context);
        $this->assertNotEmpty($result->context['conditions']);
    }
}
