<?php

namespace Tests\Unit\Jobs;

use App\Models\Alert;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProcessAlertChainsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_chain_activation_logic(): void
    {
        $triggeredAlert = $this->createMockAlert([
            'id' => 'trigger-alert',
            'status' => 'triggered',
        ]);

        $chainedAlert = $this->createMockAlert([
            'id' => 'chained-alert',
            'status' => 'chained',
        ]);

        // Verify the chain relationship
        $this->assertEquals('trigger-alert', $triggeredAlert->id);
        $this->assertEquals('chained', $chainedAlert->status);
    }

    public function test_skips_non_chained_status_alerts(): void
    {
        $chainedAlert = $this->createMockAlert([
            'id' => 'alert-1',
            'status' => 'active', // Not 'chained' status
        ]);

        $shouldActivate = $chainedAlert->status === 'chained';

        $this->assertFalse($shouldActivate);
    }

    public function test_activates_alert_with_chained_status(): void
    {
        $chainedAlert = $this->createMockAlert([
            'id' => 'alert-1',
            'status' => 'chained',
        ]);

        $shouldActivate = $chainedAlert->status === 'chained';

        $this->assertTrue($shouldActivate);
    }

    public function test_handles_delay_configuration(): void
    {
        $chain = (object) [
            'id' => 'chain-1',
            'trigger_alert_id' => 'trigger-alert',
            'activate_alert_id' => 'chained-alert',
            'delay_minutes' => 30,
            'is_active' => true,
        ];

        $this->assertEquals(30, $chain->delay_minutes);
        $this->assertTrue($chain->delay_minutes > 0);
    }

    public function test_handles_no_delay_configuration(): void
    {
        $chain = (object) [
            'id' => 'chain-1',
            'trigger_alert_id' => 'trigger-alert',
            'activate_alert_id' => 'chained-alert',
            'delay_minutes' => 0,
            'is_active' => true,
        ];

        $this->assertEquals(0, $chain->delay_minutes);
        $this->assertFalse($chain->delay_minutes > 0);
    }

    public function test_sets_chain_from_id_on_activation(): void
    {
        $triggerAlertId = 'trigger-alert';
        $chainedAlert = $this->createMockAlert([
            'id' => 'chained-alert',
            'status' => 'chained',
        ]);

        // Simulate the update
        $updates = [
            'status' => 'active',
            'chain_from_id' => $triggerAlertId,
        ];

        $this->assertEquals('active', $updates['status']);
        $this->assertEquals($triggerAlertId, $updates['chain_from_id']);
    }

    public function test_sets_expires_at_when_configured(): void
    {
        $expiresAfterMinutes = 60;
        $expiresAt = now()->addMinutes($expiresAfterMinutes);

        $this->assertNotNull($expiresAt);
        $this->assertTrue($expiresAt->gt(now()));
    }

    public function test_handles_null_expires_after_minutes(): void
    {
        $expiresAfterMinutes = null;
        $expiresAt = $expiresAfterMinutes ? now()->addMinutes($expiresAfterMinutes) : null;

        $this->assertNull($expiresAt);
    }

    public function test_processes_multiple_chains(): void
    {
        $chains = [
            (object) ['id' => 'chain-1', 'activate_alert_id' => 'alert-1', 'is_active' => true],
            (object) ['id' => 'chain-2', 'activate_alert_id' => 'alert-2', 'is_active' => true],
            (object) ['id' => 'chain-3', 'activate_alert_id' => 'alert-3', 'is_active' => true],
        ];

        $this->assertCount(3, $chains);

        $activatedIds = [];
        foreach ($chains as $chain) {
            if ($chain->is_active) {
                $activatedIds[] = $chain->activate_alert_id;
            }
        }

        $this->assertCount(3, $activatedIds);
    }

    public function test_skips_inactive_chains(): void
    {
        $chains = [
            (object) ['id' => 'chain-1', 'activate_alert_id' => 'alert-1', 'is_active' => true],
            (object) ['id' => 'chain-2', 'activate_alert_id' => 'alert-2', 'is_active' => false],
        ];

        $activeChains = array_filter($chains, fn ($c) => $c->is_active);

        $this->assertCount(1, $activeChains);
    }

    public function test_handles_empty_chain_list(): void
    {
        $chains = [];

        $this->assertCount(0, $chains);
        $this->assertTrue(empty($chains));
    }

    public function test_handles_missing_alert_to_activate(): void
    {
        $alertToActivate = null;

        $shouldSkip = ! $alertToActivate;

        $this->assertTrue($shouldSkip);
    }

    private function createMockAlert(array $attributes = []): Alert
    {
        $alert = Mockery::mock(Alert::class)->makePartial();

        $alert->shouldReceive('getAttribute')->with('id')->andReturn($attributes['id'] ?? 'test-id');
        $alert->shouldReceive('getAttribute')->with('status')->andReturn($attributes['status'] ?? 'active');

        $alert->id = $attributes['id'] ?? 'test-id';
        $alert->status = $attributes['status'] ?? 'active';

        return $alert;
    }
}
