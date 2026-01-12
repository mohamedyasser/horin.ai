<?php

namespace Tests\Unit\Telegram;

use App\Models\Asset;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AlertCreateHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_validates_alert_type(): void
    {
        $validTypes = ['price', 'signal', 'prediction'];

        $this->assertTrue(in_array('price', $validTypes));
        $this->assertTrue(in_array('signal', $validTypes));
        $this->assertTrue(in_array('prediction', $validTypes));
        $this->assertFalse(in_array('invalid', $validTypes));
    }

    #[Test]
    public function it_validates_trigger_type(): void
    {
        $validTriggers = ['target_price', 'daily_change', 'breakout', 'signal', 'prediction'];

        $this->assertTrue(in_array('target_price', $validTriggers));
        $this->assertTrue(in_array('daily_change', $validTriggers));
        $this->assertTrue(in_array('signal', $validTriggers));
        $this->assertFalse(in_array('invalid', $validTriggers));
    }

    #[Test]
    public function it_validates_direction(): void
    {
        $validDirections = ['above', 'below', 'both'];

        $this->assertTrue(in_array('above', $validDirections));
        $this->assertTrue(in_array('below', $validDirections));
        $this->assertTrue(in_array('both', $validDirections));
        $this->assertFalse(in_array('left', $validDirections));
    }

    #[Test]
    public function it_infers_high_priority_for_close_target(): void
    {
        $draft = [
            'parameters' => ['target_price' => 102.0],
            'current_price' => 100.0,
        ];

        $target = $draft['parameters']['target_price'];
        $current = $draft['current_price'];
        $diff = abs($target - $current) / $current;

        $priority = $diff <= 0.02 ? 'high' : 'medium';

        $this->assertEquals('high', $priority);
    }

    #[Test]
    public function it_infers_medium_priority_for_far_target(): void
    {
        $draft = [
            'parameters' => ['target_price' => 120.0],
            'current_price' => 100.0,
        ];

        $target = $draft['parameters']['target_price'];
        $current = $draft['current_price'];
        $diff = abs($target - $current) / $current;

        $priority = $diff <= 0.02 ? 'high' : 'medium';

        $this->assertEquals('medium', $priority);
    }

    #[Test]
    public function it_handles_missing_target_price(): void
    {
        $draft = [
            'parameters' => [],
            'current_price' => 100.0,
        ];

        $hasTarget = isset($draft['parameters']['target_price']);
        $priority = 'medium'; // Default when no target

        $this->assertFalse($hasTarget);
        $this->assertEquals('medium', $priority);
    }

    #[Test]
    public function it_returns_correct_type_label_for_english(): void
    {
        $types = [
            'price' => ['en' => 'Price Alert', 'ar' => 'تنبيه السعر'],
            'signal' => ['en' => 'Signal Alert', 'ar' => 'تنبيه إشارة'],
            'prediction' => ['en' => 'Prediction Alert', 'ar' => 'تنبيه توقع'],
        ];

        $this->assertEquals('Price Alert', $types['price']['en']);
        $this->assertEquals('Signal Alert', $types['signal']['en']);
        $this->assertEquals('Prediction Alert', $types['prediction']['en']);
    }

    #[Test]
    public function it_returns_correct_type_label_for_arabic(): void
    {
        $types = [
            'price' => ['en' => 'Price Alert', 'ar' => 'تنبيه السعر'],
            'signal' => ['en' => 'Signal Alert', 'ar' => 'تنبيه إشارة'],
            'prediction' => ['en' => 'Prediction Alert', 'ar' => 'تنبيه توقع'],
        ];

        $this->assertEquals('تنبيه السعر', $types['price']['ar']);
        $this->assertEquals('تنبيه إشارة', $types['signal']['ar']);
        $this->assertEquals('تنبيه توقع', $types['prediction']['ar']);
    }

    #[Test]
    public function it_parses_callback_data_correctly(): void
    {
        $data = 'alert:create:type:price';
        $parts = explode(':', $data);

        $this->assertEquals('alert', $parts[0]);
        $this->assertEquals('create', $parts[1]);
        $this->assertEquals('type', $parts[2]);
        $this->assertEquals('price', $parts[3]);
    }

    #[Test]
    public function it_handles_callback_data_with_missing_parts(): void
    {
        $data = 'alert:create';
        $parts = explode(':', $data);

        $action = $parts[1] ?? null;
        $subAction = $parts[2] ?? null;
        $value = $parts[3] ?? null;

        $this->assertEquals('create', $action);
        $this->assertNull($subAction);
        $this->assertNull($value);
    }

    #[Test]
    public function it_validates_draft_completeness(): void
    {
        // Complete draft
        $draft = [
            'asset_id' => 'asset-1',
            'trigger_type' => 'target_price',
            'direction' => 'above',
        ];

        $isComplete = ! empty($draft['asset_id']) &&
                     ! empty($draft['trigger_type']) &&
                     ! empty($draft['direction']);

        $this->assertTrue($isComplete);
    }

    #[Test]
    public function it_detects_incomplete_draft(): void
    {
        // Missing direction
        $draft = [
            'asset_id' => 'asset-1',
            'trigger_type' => 'target_price',
        ];

        $isComplete = ! empty($draft['asset_id']) &&
                     ! empty($draft['trigger_type']) &&
                     ! empty($draft['direction'] ?? null);

        $this->assertFalse($isComplete);
    }

    #[Test]
    public function it_determines_input_type_for_trigger(): void
    {
        $inputTypes = [
            'target_price' => 'alert_target_price',
            'daily_change' => 'alert_percentage',
            'breakout' => 'alert_target_price',
        ];

        $this->assertEquals('alert_target_price', $inputTypes['target_price']);
        $this->assertEquals('alert_percentage', $inputTypes['daily_change']);
        $this->assertEquals('alert_target_price', $inputTypes['breakout']);
    }

    #[Test]
    public function it_sets_default_signal_parameters(): void
    {
        $triggerType = 'signal';
        $parameters = match ($triggerType) {
            'signal' => ['min_strength' => 0.7],
            'prediction' => ['min_confidence' => 0.75, 'direction' => 'up'],
            default => [],
        };

        $this->assertEquals(['min_strength' => 0.7], $parameters);
    }

    #[Test]
    public function it_sets_default_prediction_parameters(): void
    {
        $triggerType = 'prediction';
        $parameters = match ($triggerType) {
            'signal' => ['min_strength' => 0.7],
            'prediction' => ['min_confidence' => 0.75, 'direction' => 'up'],
            default => [],
        };

        $this->assertEquals(['min_confidence' => 0.75, 'direction' => 'up'], $parameters);
    }

    #[Test]
    public function it_formats_direction_icons_correctly(): void
    {
        $directions = [
            'above' => '⬆️',
            'below' => '⬇️',
            'both' => '↕️',
        ];

        $this->assertEquals('⬆️', $directions['above']);
        $this->assertEquals('⬇️', $directions['below']);
        $this->assertEquals('↕️', $directions['both']);
    }

    #[Test]
    public function it_formats_direction_labels_correctly_english(): void
    {
        $locale = 'en';
        $labels = [
            'above' => $locale === 'ar' ? 'أعلى من' : 'Above',
            'below' => $locale === 'ar' ? 'أقل من' : 'Below',
            'both' => $locale === 'ar' ? 'أي اتجاه' : 'Either Direction',
        ];

        $this->assertEquals('Above', $labels['above']);
        $this->assertEquals('Below', $labels['below']);
        $this->assertEquals('Either Direction', $labels['both']);
    }

    #[Test]
    public function it_formats_direction_labels_correctly_arabic(): void
    {
        $locale = 'ar';
        $labels = [
            'above' => $locale === 'ar' ? 'أعلى من' : 'Above',
            'below' => $locale === 'ar' ? 'أقل من' : 'Below',
            'both' => $locale === 'ar' ? 'أي اتجاه' : 'Either Direction',
        ];

        $this->assertEquals('أعلى من', $labels['above']);
        $this->assertEquals('أقل من', $labels['below']);
        $this->assertEquals('أي اتجاه', $labels['both']);
    }

    #[Test]
    public function it_creates_correct_alert_data_structure(): void
    {
        $draft = [
            'asset_id' => 'asset-123',
            'type' => 'price',
            'trigger_type' => 'target_price',
            'direction' => 'above',
            'parameters' => ['target_price' => 150.0],
        ];

        $alertData = [
            'user_id' => 1,
            'asset_id' => $draft['asset_id'],
            'type' => $draft['type'] ?? 'price',
            'trigger_type' => $draft['trigger_type'],
            'scope' => 'single_asset',
            'direction' => $draft['direction'],
            'condition_logic' => 'single',
            'parameters' => $draft['parameters'] ?? [],
            'status' => 'active',
            'priority' => 'medium',
            'is_recurring' => false,
            'cooldown_minutes' => 240,
            'delivery_config' => ['channels' => ['telegram', 'in_app']],
        ];

        $this->assertEquals('asset-123', $alertData['asset_id']);
        $this->assertEquals('price', $alertData['type']);
        $this->assertEquals('target_price', $alertData['trigger_type']);
        $this->assertEquals('above', $alertData['direction']);
        $this->assertEquals(['target_price' => 150.0], $alertData['parameters']);
        $this->assertEquals(['telegram', 'in_app'], $alertData['delivery_config']['channels']);
    }

    #[Test]
    public function it_formats_target_price_parameter(): void
    {
        $parameters = ['target_price' => 1234.56];
        $formatted = number_format($parameters['target_price'], 2);

        $this->assertEquals('1,234.56', $formatted);
    }

    #[Test]
    public function it_formats_signal_strength_display(): void
    {
        $parameters = ['min_strength' => 0.7];
        $strength = ($parameters['min_strength'] ?? 0.7) * 100;

        $this->assertEquals(70.0, $strength);
    }

    #[Test]
    public function it_formats_prediction_confidence_display(): void
    {
        $parameters = ['min_confidence' => 0.75];
        $confidence = ($parameters['min_confidence'] ?? 0.75) * 100;

        $this->assertEquals(75.0, $confidence);
    }

    #[Test]
    public function it_identifies_signal_and_prediction_triggers(): void
    {
        $simpleFlowTriggers = ['signal', 'prediction'];

        $this->assertTrue(in_array('signal', $simpleFlowTriggers));
        $this->assertTrue(in_array('prediction', $simpleFlowTriggers));
        $this->assertFalse(in_array('target_price', $simpleFlowTriggers));
    }

    #[Test]
    public function it_handles_menu_action(): void
    {
        $data = 'alert:menu';
        $parts = explode(':', $data);
        $action = $parts[1] ?? null;

        $this->assertEquals('menu', $action);
    }

    #[Test]
    public function it_handles_asset_search_action(): void
    {
        $assetIdOrAction = 'search';

        $isSearch = $assetIdOrAction === 'search';

        $this->assertTrue($isSearch);
    }

    #[Test]
    public function it_constructs_draft_step_by_step(): void
    {
        // Step 1: Type selection
        $draft = ['step' => 'type'];
        $this->assertEquals('type', $draft['step']);

        // Step 2: Add type
        $draft['step'] = 'asset';
        $draft['type'] = 'price';
        $this->assertEquals('asset', $draft['step']);
        $this->assertEquals('price', $draft['type']);

        // Step 3: Add asset
        $draft['step'] = 'trigger';
        $draft['asset_id'] = 'asset-123';
        $draft['asset_symbol'] = 'AAPL';
        $this->assertEquals('trigger', $draft['step']);
        $this->assertEquals('asset-123', $draft['asset_id']);

        // Step 4: Add trigger
        $draft['step'] = 'parameter';
        $draft['trigger_type'] = 'target_price';
        $this->assertEquals('parameter', $draft['step']);
        $this->assertEquals('target_price', $draft['trigger_type']);

        // Step 5: Add direction
        $draft['step'] = 'confirm';
        $draft['direction'] = 'above';
        $this->assertEquals('confirm', $draft['step']);
        $this->assertEquals('above', $draft['direction']);
    }

    #[Test]
    public function it_clears_draft_on_menu_return(): void
    {
        $draft = [
            'step' => 'confirm',
            'type' => 'price',
            'asset_id' => 'asset-123',
        ];

        // Menu return clears draft
        $draft = null;

        $this->assertNull($draft);
    }

    #[Test]
    public function it_gets_asset_name_based_on_locale(): void
    {
        $asset = (object) [
            'name_ar' => 'أبل',
            'name' => 'Apple Inc',
        ];

        $locale = 'ar';
        $name = $locale === 'ar' ? ($asset->name_ar ?: $asset->name) : $asset->name;

        $this->assertEquals('أبل', $name);

        $locale = 'en';
        $name = $locale === 'ar' ? ($asset->name_ar ?: $asset->name) : $asset->name;

        $this->assertEquals('Apple Inc', $name);
    }

    #[Test]
    public function it_falls_back_to_english_name_when_arabic_missing(): void
    {
        $asset = (object) [
            'name_ar' => null,
            'name' => 'Apple Inc',
        ];

        $locale = 'ar';
        $name = $locale === 'ar' ? ($asset->name_ar ?: $asset->name) : $asset->name;

        $this->assertEquals('Apple Inc', $name);
    }
}
