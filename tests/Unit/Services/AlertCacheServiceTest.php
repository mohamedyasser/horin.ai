<?php

namespace Tests\Unit\Services;

use App\Services\AlertCacheService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertCacheServiceTest extends TestCase
{
    protected AlertCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AlertCacheService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_cached_alerts_for_asset(): void
    {
        $assetId = 'asset-123';
        $cachedData = [
            ['id' => 'alert-1', 'asset_id' => $assetId, 'type' => 'price'],
            ['id' => 'alert-2', 'asset_id' => $assetId, 'type' => 'price'],
        ];

        Cache::shouldReceive('get')
            ->with("active_alerts:asset:{$assetId}")
            ->andReturn($cachedData);

        $result = $this->service->getAlertsForAsset($assetId);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function it_returns_cached_alerts_by_type(): void
    {
        $type = 'signal';
        $cachedData = [
            ['id' => 'alert-1', 'type' => $type],
            ['id' => 'alert-2', 'type' => $type],
        ];

        Cache::shouldReceive('get')
            ->with("active_alerts:type:{$type}")
            ->andReturn($cachedData);

        $result = $this->service->getAlertsByType($type);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function it_invalidates_type_cache(): void
    {
        $type = 'prediction';

        Cache::shouldReceive('forget')
            ->with("active_alerts:type:{$type}")
            ->once();

        $this->service->invalidateType($type);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_checks_if_asset_has_active_alerts(): void
    {
        $assetId = 'asset-123';

        Redis::shouldReceive('sismember')
            ->with('active_alert_assets', $assetId)
            ->andReturn(1);

        $result = $this->service->hasActiveAlerts($assetId);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_asset_has_no_active_alerts(): void
    {
        $assetId = 'asset-456';

        Redis::shouldReceive('sismember')
            ->with('active_alert_assets', $assetId)
            ->andReturn(0);

        $result = $this->service->hasActiveAlerts($assetId);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_gets_assets_with_active_alerts(): void
    {
        $assetIds = ['asset-1', 'asset-2', 'asset-3'];

        Redis::shouldReceive('smembers')
            ->with('active_alert_assets')
            ->andReturn($assetIds);

        $result = $this->service->getAssetsWithActiveAlerts();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContains('asset-1', $result);
    }

    #[Test]
    public function it_returns_empty_array_when_no_assets_have_alerts(): void
    {
        Redis::shouldReceive('smembers')
            ->with('active_alert_assets')
            ->andReturn(null);

        $result = $this->service->getAssetsWithActiveAlerts();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_gets_cache_stats(): void
    {
        Redis::shouldReceive('smembers')
            ->with('active_alert_assets')
            ->andReturn(['asset-1', 'asset-2']);

        $stats = $this->service->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('assets_with_alerts', $stats);
        $this->assertArrayHasKey('cache_ttl_seconds', $stats);
        $this->assertEquals(2, $stats['assets_with_alerts']);
        $this->assertEquals(60, $stats['cache_ttl_seconds']);
    }
}
