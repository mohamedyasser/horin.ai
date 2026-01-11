<?php

namespace Tests\Unit\Services;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\AlertNotification;
use App\Models\Asset;
use App\Services\TelegramMessageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramMessageBuilderTest extends TestCase
{
    use RefreshDatabase;

    private TelegramMessageBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new TelegramMessageBuilder();
    }

    public function test_it_builds_target_price_message_in_english(): void
    {
        $asset = Asset::factory()->create([
            'symbol' => 'COMI',
            'name' => 'Commercial International Bank',
        ]);

        $alert = Alert::factory()->create([
            'asset_id' => $asset->id,
            'trigger_type' => 'target_price',
            'parameters' => ['target_price' => 52.00, 'direction' => 'above'],
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'asset_id' => $asset->id,
            'trigger_value' => 52.50,
            'trigger_context' => ['change_percent' => 4.2],
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
            'user_id' => $alert->user_id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'en');

        $this->assertArrayHasKey('text', $result);
        $this->assertArrayHasKey('keyboard', $result);
        $this->assertStringContainsString('Target Price Reached', $result['text']);
        $this->assertStringContainsString('COMI', $result['text']);
        $this->assertStringContainsString('52.5', $result['text']);
    }

    public function test_it_builds_target_price_message_in_arabic(): void
    {
        $asset = Asset::factory()->create([
            'symbol' => 'COMI',
            'name' => 'Commercial International Bank',
            'name_ar' => 'البنك التجاري الدولي',
        ]);

        $alert = Alert::factory()->create([
            'asset_id' => $asset->id,
            'trigger_type' => 'target_price',
            'parameters' => ['target_price' => 52.00],
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'asset_id' => $asset->id,
            'trigger_value' => 52.50,
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
            'user_id' => $alert->user_id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'ar');

        $this->assertStringContainsString('وصول السعر المستهدف', $result['text']);
        $this->assertStringContainsString('ج.م', $result['text']);
    }

    public function test_it_builds_signal_message(): void
    {
        $asset = Asset::factory()->create(['symbol' => 'HRHO']);

        $alert = Alert::factory()->create([
            'asset_id' => $asset->id,
            'trigger_type' => 'signal',
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'asset_id' => $asset->id,
            'trigger_value' => 15.50,
            'trigger_context' => [
                'indicator' => 'RSI',
                'signal_type' => 'Oversold',
                'strength' => 0.85,
                'indicator_value' => 28.5,
            ],
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
            'user_id' => $alert->user_id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'en');

        $this->assertStringContainsString('Technical Signal', $result['text']);
        $this->assertStringContainsString('RSI', $result['text']);
    }

    public function test_it_includes_action_buttons_in_keyboard(): void
    {
        $asset = Asset::factory()->create(['symbol' => 'TEST']);

        $alert = Alert::factory()->create([
            'asset_id' => $asset->id,
            'trigger_type' => 'target_price',
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'asset_id' => $asset->id,
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
            'user_id' => $alert->user_id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'en');

        $keyboard = $result['keyboard'];

        $this->assertIsArray($keyboard);
        $this->assertNotEmpty($keyboard);

        // Should have View Stock button
        $viewButton = $keyboard[0][0] ?? null;
        $this->assertNotNull($viewButton);
        $this->assertStringContainsString('View Stock', $viewButton['text']);

        // Should have Snooze buttons
        $snoozeRow = $keyboard[1] ?? null;
        $this->assertNotNull($snoozeRow);
        $this->assertStringContainsString('Snooze', $snoozeRow[0]['text']);
    }

    public function test_it_builds_breakout_message(): void
    {
        $asset = Asset::factory()->create(['symbol' => 'EFIH']);

        $alert = Alert::factory()->breakout(100.0, 'above')->create([
            'asset_id' => $asset->id,
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'asset_id' => $asset->id,
            'trigger_value' => 102.50,
            'trigger_context' => ['volume_ratio' => 2.5],
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
            'user_id' => $alert->user_id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'en');

        $this->assertStringContainsString('Breakout Confirmed', $result['text']);
        $this->assertStringContainsString('EFIH', $result['text']);
    }

    public function test_it_builds_prediction_message(): void
    {
        $asset = Asset::factory()->create(['symbol' => 'TMGH']);

        $alert = Alert::factory()->prediction()->create([
            'asset_id' => $asset->id,
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'asset_id' => $asset->id,
            'trigger_value' => 45.00,
            'trigger_context' => [
                'direction' => 'up',
                'confidence' => 0.85,
                'horizon' => '1 hour',
            ],
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
            'user_id' => $alert->user_id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'en');

        $this->assertStringContainsString('AI Prediction', $result['text']);
        $this->assertStringContainsString('85%', $result['text']);
    }
}
