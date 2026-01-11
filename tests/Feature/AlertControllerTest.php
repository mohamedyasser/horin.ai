<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_alerts(): void
    {
        $response = $this->get(route('alerts.index'));
        $response->assertRedirect();
    }

    public function test_user_can_view_their_alerts(): void
    {
        $user = User::factory()->create();
        Alert::factory()->count(3)->create(['user_id' => $user->id]);
        Alert::factory()->count(2)->create(); // Other user's alerts

        $response = $this->actingAs($user)->get(route('alerts.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 3)
        );
    }

    public function test_user_can_view_create_alert_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('alerts.create'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Alerts/Create')
                ->has('alertTypes')
        );
    }

    public function test_user_can_create_price_alert(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $response = $this->actingAs($user)->post(route('alerts.store'), [
            'asset_id' => $asset->id,
            'type' => 'price',
            'trigger_type' => 'target_price',
            'scope' => 'single_asset',
            'direction' => 'above',
            'condition_logic' => 'single',
            'parameters' => ['target_price' => 50.00],
            'is_recurring' => false,
            'market_hours_only' => true,
        ]);

        $response->assertRedirect(route('alerts.index'));
        $this->assertDatabaseHas('alerts', [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'trigger_type' => 'target_price',
        ]);
    }

    public function test_user_can_view_own_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('alerts.show', $alert));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Alerts/Show')
                ->has('alert')
        );
    }

    public function test_user_cannot_view_others_alerts(): void
    {
        $user = User::factory()->create();
        $otherAlert = Alert::factory()->create();

        $response = $this->actingAs($user)->get(route('alerts.show', $otherAlert));

        $response->assertForbidden();
    }

    public function test_user_can_edit_own_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('alerts.edit', $alert));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Alerts/Edit')
                ->has('alert')
        );
    }

    public function test_user_can_update_own_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('alerts.update', $alert), [
            'priority' => 'high',
            'parameters' => ['target_price' => 60.00],
        ]);

        $response->assertRedirect(route('alerts.index'));
        $alert->refresh();
        $this->assertEquals('high', $alert->priority);
    }

    public function test_user_cannot_update_others_alerts(): void
    {
        $user = User::factory()->create();
        $otherAlert = Alert::factory()->create();

        $response = $this->actingAs($user)->put(route('alerts.update', $otherAlert), [
            'priority' => 'high',
        ]);

        $response->assertForbidden();
    }

    public function test_user_can_delete_own_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('alerts.destroy', $alert));

        $response->assertRedirect();
        $this->assertSoftDeleted('alerts', ['id' => $alert->id]);
    }

    public function test_user_can_snooze_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('alerts.snooze', $alert), [
            'preset' => '1h',
        ]);

        $response->assertRedirect();
        $alert->refresh();
        $this->assertNotNull($alert->snoozed_until);
        $this->assertTrue($alert->snoozed_until->gt(now()));
    }

    public function test_user_can_unsnooze_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->snoozed()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('alerts.unsnooze', $alert));

        $response->assertRedirect();
        $alert->refresh();
        $this->assertNull($alert->snoozed_until);
    }

    public function test_user_can_duplicate_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('alerts.duplicate', $alert));

        $response->assertRedirect();
        $this->assertDatabaseCount('alerts', 2);
    }

    public function test_validation_requires_target_price_for_target_price_alerts(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $response = $this->actingAs($user)->post(route('alerts.store'), [
            'asset_id' => $asset->id,
            'type' => 'price',
            'trigger_type' => 'target_price',
            'scope' => 'single_asset',
            'direction' => 'above',
            'condition_logic' => 'single',
            'parameters' => [],
        ]);

        $response->assertSessionHasErrors('parameters.target_price');
    }

    public function test_validation_requires_valid_type(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $response = $this->actingAs($user)->post(route('alerts.store'), [
            'asset_id' => $asset->id,
            'type' => 'invalid_type',
            'trigger_type' => 'target_price',
            'scope' => 'single_asset',
            'direction' => 'above',
            'condition_logic' => 'single',
            'parameters' => ['target_price' => 50.00],
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_alerts_filtered_by_type(): void
    {
        $user = User::factory()->create();
        Alert::factory()->count(2)->create(['user_id' => $user->id, 'type' => 'price']);
        Alert::factory()->count(3)->create(['user_id' => $user->id, 'type' => 'signal']);

        $response = $this->actingAs($user)->get(route('alerts.index', ['type' => 'price']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->has('alerts.data', 2)
        );
    }

    public function test_alerts_filtered_by_status(): void
    {
        $user = User::factory()->create();
        Alert::factory()->count(2)->active()->create(['user_id' => $user->id]);
        Alert::factory()->count(3)->triggered()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('alerts.index', ['status' => 'active']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->has('alerts.data', 2)
        );
    }
}
