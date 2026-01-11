<?php

namespace Tests\Feature\Api;

use App\Models\AlertNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_notifications_api(): void
    {
        $response = $this->getJson('/api/notifications');
        $response->assertUnauthorized();
    }

    public function test_user_can_get_notifications(): void
    {
        $user = User::factory()->create();
        AlertNotification::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'channel', 'priority', 'title', 'body', 'status', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_user_only_sees_own_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        AlertNotification::factory()->count(3)->create(['user_id' => $user->id]);
        AlertNotification::factory()->count(5)->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_get_unread_count(): void
    {
        $user = User::factory()->create();
        AlertNotification::factory()->count(3)->unread()->create(['user_id' => $user->id]);
        AlertNotification::factory()->count(2)->read()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $response->assertOk()
            ->assertJson(['count' => 3]);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = AlertNotification::factory()->unread()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
        $this->assertEquals('read', $notification->status);
    }

    public function test_user_cannot_mark_others_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = AlertNotification::factory()->unread()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertForbidden();
    }

    public function test_user_can_mark_all_as_read(): void
    {
        $user = User::factory()->create();
        AlertNotification::factory()->count(5)->unread()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/read-all');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(
            0,
            AlertNotification::where('user_id', $user->id)->whereNull('read_at')->count()
        );
    }

    public function test_mark_all_as_read_only_affects_own_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        AlertNotification::factory()->count(3)->unread()->create(['user_id' => $user->id]);
        AlertNotification::factory()->count(5)->unread()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/read-all');

        $this->assertEquals(
            5,
            AlertNotification::where('user_id', $otherUser->id)->whereNull('read_at')->count()
        );
    }

    public function test_notifications_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        AlertNotification::factory()->count(2)->pending()->create(['user_id' => $user->id]);
        AlertNotification::factory()->count(3)->sent()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?status=pending');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_notifications_pagination_works(): void
    {
        $user = User::factory()->create();
        AlertNotification::factory()->count(25)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);
    }
}
