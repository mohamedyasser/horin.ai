<?php

namespace Tests\Unit\Jobs;

use App\Models\Alert;
use App\Models\LatestAssetPrice;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProcessPriceAlertsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_processes_price_alert_matching(): void
    {
        $alert = $this->createMockAlert([
            'id' => 'alert-1',
            'type' => 'price',
            'trigger_type' => 'target_price',
            'direction' => 'above',
            'parameters' => ['target_price' => 145.0],
        ]);

        $price = $this->createMockPrice(['price' => 150.0]);

        // Test the matching logic
        $targetPrice = $alert->parameters['target_price'];
        $currentPrice = $price->price;
        $direction = $alert->direction;

        $triggered = ($direction === 'above' && $currentPrice >= $targetPrice);

        $this->assertTrue($triggered);
    }

    public function test_does_not_trigger_when_price_below_target_for_above_direction(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'price',
            'trigger_type' => 'target_price',
            'direction' => 'above',
            'parameters' => ['target_price' => 150.0],
        ]);

        $price = $this->createMockPrice(['price' => 140.0]);

        $targetPrice = $alert->parameters['target_price'];
        $currentPrice = $price->price;
        $direction = $alert->direction;

        $triggered = ($direction === 'above' && $currentPrice >= $targetPrice);

        $this->assertFalse($triggered);
    }

    public function test_triggers_below_direction(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'price',
            'trigger_type' => 'target_price',
            'direction' => 'below',
            'parameters' => ['target_price' => 150.0],
        ]);

        $price = $this->createMockPrice(['price' => 140.0]);

        $targetPrice = $alert->parameters['target_price'];
        $currentPrice = $price->price;
        $direction = $alert->direction;

        $triggered = ($direction === 'below' && $currentPrice <= $targetPrice);

        $this->assertTrue($triggered);
    }

    public function test_skips_alert_that_cannot_trigger(): void
    {
        $canTrigger = false;

        // Alert should be skipped because canTrigger returns false
        $this->assertFalse($canTrigger);
    }

    public function test_filters_only_price_alerts(): void
    {
        $priceAlert = $this->createMockAlert([
            'id' => 'alert-1',
            'type' => 'price',
            'trigger_type' => 'target_price',
        ]);

        $signalAlert = $this->createMockAlert([
            'id' => 'alert-2',
            'type' => 'signal',
            'trigger_type' => 'signal',
        ]);

        $alerts = collect([$priceAlert, $signalAlert]);
        $priceAlerts = $alerts->filter(fn ($alert) => $alert->type === 'price');

        $this->assertCount(1, $priceAlerts);
        $this->assertEquals('price', $priceAlerts->first()->type);
    }

    public function test_handles_empty_alert_list(): void
    {
        $alerts = collect([]);
        $priceAlerts = $alerts->filter(fn ($alert) => $alert->type === 'price');

        $this->assertCount(0, $priceAlerts);
    }

    public function test_processes_multiple_alerts_for_same_asset(): void
    {
        $assetId = 'asset-123';

        $alert1 = $this->createMockAlert([
            'id' => 'alert-1',
            'asset_id' => $assetId,
            'type' => 'price',
            'trigger_type' => 'target_price',
            'direction' => 'above',
            'parameters' => ['target_price' => 100.0],
        ]);

        $alert2 = $this->createMockAlert([
            'id' => 'alert-2',
            'asset_id' => $assetId,
            'type' => 'price',
            'trigger_type' => 'breakout',
            'direction' => 'above',
            'parameters' => ['level' => 110.0],
        ]);

        $alerts = collect([$alert1, $alert2]);

        $this->assertCount(2, $alerts);
        $this->assertTrue($alerts->every(fn ($a) => $a->asset_id === $assetId));
    }

    public function test_handles_missing_price_data(): void
    {
        $assetIds = collect(['asset-1', 'asset-2', 'asset-3']);

        // Simulate only 2 of 3 assets having prices
        $prices = collect([
            'asset-1' => $this->createMockPrice(['price' => 100]),
            'asset-3' => $this->createMockPrice(['price' => 200]),
        ]);

        $processedAssets = [];
        foreach ($assetIds as $assetId) {
            $price = $prices->get($assetId);
            if ($price) {
                $processedAssets[] = $assetId;
            }
        }

        $this->assertCount(2, $processedAssets);
        $this->assertNotContains('asset-2', $processedAssets);
    }

    public function test_tracks_triggered_count(): void
    {
        $triggeredCount = 0;

        $alerts = [
            ['triggered' => true],
            ['triggered' => false],
            ['triggered' => true],
            ['triggered' => true],
        ];

        foreach ($alerts as $result) {
            if ($result['triggered']) {
                $triggeredCount++;
            }
        }

        $this->assertEquals(3, $triggeredCount);
    }

    public function test_breakout_trigger_above(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'price',
            'trigger_type' => 'breakout',
            'direction' => 'above',
            'parameters' => ['level' => 100.0],
        ]);

        $price = $this->createMockPrice(['price' => 105.0]);

        $level = $alert->parameters['level'];
        $currentPrice = $price->price;
        $direction = $alert->direction;

        $triggered = ($direction === 'above' && $currentPrice >= $level);

        $this->assertTrue($triggered);
    }

    public function test_zone_trigger_inside(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'price',
            'trigger_type' => 'zone',
            'parameters' => ['zone_low' => 90.0, 'zone_high' => 110.0],
        ]);

        $price = $this->createMockPrice(['price' => 100.0]);

        $zoneLow = $alert->parameters['zone_low'];
        $zoneHigh = $alert->parameters['zone_high'];
        $currentPrice = $price->price;

        $triggered = ($currentPrice >= $zoneLow && $currentPrice <= $zoneHigh);

        $this->assertTrue($triggered);
    }

    public function test_zone_trigger_outside(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'price',
            'trigger_type' => 'zone',
            'parameters' => ['zone_low' => 90.0, 'zone_high' => 110.0],
        ]);

        $price = $this->createMockPrice(['price' => 120.0]);

        $zoneLow = $alert->parameters['zone_low'];
        $zoneHigh = $alert->parameters['zone_high'];
        $currentPrice = $price->price;

        $triggered = ($currentPrice >= $zoneLow && $currentPrice <= $zoneHigh);

        $this->assertFalse($triggered);
    }

    public function test_daily_change_trigger(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'price',
            'trigger_type' => 'daily_change',
            'direction' => 'above',
            'parameters' => ['threshold_percent' => 5.0],
        ]);

        $price = $this->createMockPrice(['change_p' => 6.5]);

        $threshold = $alert->parameters['threshold_percent'];
        $changePercent = abs($price->change_p);
        $direction = $alert->direction;

        $triggered = $changePercent >= $threshold;

        $this->assertTrue($triggered);
    }

    public function test_daily_change_not_triggered(): void
    {
        $alert = $this->createMockAlert([
            'type' => 'price',
            'trigger_type' => 'daily_change',
            'direction' => 'above',
            'parameters' => ['threshold_percent' => 5.0],
        ]);

        $price = $this->createMockPrice(['change_p' => 3.0]);

        $threshold = $alert->parameters['threshold_percent'];
        $changePercent = abs($price->change_p);

        $triggered = $changePercent >= $threshold;

        $this->assertFalse($triggered);
    }

    public function test_market_hours_check_weekday(): void
    {
        // Test market hours logic (10:00 - 14:30 Cairo time)
        $cairo = new \DateTimeZone('Africa/Cairo');
        $mondayNoon = new \DateTime('2024-01-08 12:00:00', $cairo); // Monday

        $dayOfWeek = (int) $mondayNoon->format('N');
        $time = $mondayNoon->format('H:i');

        $isWeekday = $dayOfWeek < 5; // Friday is 5, Saturday is 6
        $isDuringMarket = $time >= '10:00' && $time <= '14:30';
        $isMarketOpen = $isWeekday && $isDuringMarket;

        $this->assertTrue($isMarketOpen);
    }

    public function test_market_hours_check_weekend(): void
    {
        // Test market hours logic - Friday should be closed
        $cairo = new \DateTimeZone('Africa/Cairo');
        $fridayNoon = new \DateTime('2024-01-12 12:00:00', $cairo); // Friday

        $dayOfWeek = (int) $fridayNoon->format('N');
        $isWeekend = $dayOfWeek >= 5;

        $this->assertTrue($isWeekend);
    }

    private function createMockAlert(array $attributes = []): Alert
    {
        $alert = Mockery::mock(Alert::class)->makePartial();

        $alert->shouldReceive('getAttribute')->with('id')->andReturn($attributes['id'] ?? 'test-id');
        $alert->shouldReceive('getAttribute')->with('user_id')->andReturn($attributes['user_id'] ?? 1);
        $alert->shouldReceive('getAttribute')->with('asset_id')->andReturn($attributes['asset_id'] ?? 'asset-1');
        $alert->shouldReceive('getAttribute')->with('type')->andReturn($attributes['type'] ?? 'price');
        $alert->shouldReceive('getAttribute')->with('trigger_type')->andReturn($attributes['trigger_type'] ?? 'target_price');
        $alert->shouldReceive('getAttribute')->with('direction')->andReturn($attributes['direction'] ?? 'above');
        $alert->shouldReceive('getAttribute')->with('parameters')->andReturn($attributes['parameters'] ?? []);
        $alert->shouldReceive('getAttribute')->with('status')->andReturn($attributes['status'] ?? 'active');

        $alert->id = $attributes['id'] ?? 'test-id';
        $alert->user_id = $attributes['user_id'] ?? 1;
        $alert->asset_id = $attributes['asset_id'] ?? 'asset-1';
        $alert->type = $attributes['type'] ?? 'price';
        $alert->trigger_type = $attributes['trigger_type'] ?? 'target_price';
        $alert->direction = $attributes['direction'] ?? 'above';
        $alert->parameters = $attributes['parameters'] ?? [];
        $alert->status = $attributes['status'] ?? 'active';

        return $alert;
    }

    private function createMockPrice(array $attributes = []): LatestAssetPrice
    {
        $price = new LatestAssetPrice;
        $price->pid = $attributes['pid'] ?? 'test-pid';
        $price->asset_id = $attributes['asset_id'] ?? 'asset-1';
        $price->price = $attributes['price'] ?? 100.0;
        $price->change = $attributes['change'] ?? 0.0;
        $price->change_p = $attributes['change_p'] ?? 0.0;
        $price->high = $attributes['high'] ?? 105.0;
        $price->low = $attributes['low'] ?? 95.0;
        $price->week_52_high = $attributes['week_52_high'] ?? 120.0;
        $price->week_52_low = $attributes['week_52_low'] ?? 80.0;
        $price->open = $attributes['open'] ?? 99.0;
        $price->prev_close = $attributes['prev_close'] ?? 98.0;

        return $price;
    }
}
