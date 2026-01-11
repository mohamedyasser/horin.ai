<?php

namespace Tests\Feature\Auth;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use WeStacks\TeleBot\Laravel\TeleBot;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TeleBot::fake();
    }

    public function test_webhook_rejects_request_without_secret_token(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => 'test-secret-token']);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 123456789],
                'text' => '/start',
            ],
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    public function test_webhook_rejects_request_with_invalid_secret_token(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => 'test-secret-token']);

        $response = $this->postJson(
            route('telegram.webhook'),
            [
                'update_id' => 123456789,
                'message' => [
                    'message_id' => 1,
                    'chat' => ['id' => 123456789],
                    'text' => '/start',
                ],
            ],
            ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-token']
        );

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized']);
    }

    public function test_webhook_accepts_request_with_valid_secret_token(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => 'test-secret-token']);

        $response = $this->postJson(
            route('telegram.webhook'),
            [
                'update_id' => 123456789,
                'message' => [
                    'message_id' => 1,
                    'chat' => ['id' => 123456789],
                    'text' => 'Hello',
                ],
            ],
            ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret-token']
        );

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_allows_request_when_no_secret_configured(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 123456789],
                'text' => '/start',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_handles_start_command(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                    'username' => 'testuser',
                ],
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/start',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_handles_help_command(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $user = User::factory()->create([
            'telegram_id' => '123456789',
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456790,
            'message' => [
                'message_id' => 2,
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/help',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_handles_alerts_command(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $user = User::factory()->create([
            'telegram_id' => '123456789',
        ]);

        Alert::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456791,
            'message' => [
                'message_id' => 3,
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/alerts',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_handles_callback_query(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $user = User::factory()->create([
            'telegram_id' => '123456789',
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456792,
            'callback_query' => [
                'id' => 'callback_123',
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                ],
                'message' => [
                    'message_id' => 5,
                    'chat' => [
                        'id' => 123456789,
                        'type' => 'private',
                    ],
                ],
                'data' => 'lang:ar',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_handles_snooze_callback(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $user = User::factory()->create([
            'telegram_id' => '123456789',
        ]);

        $alert = Alert::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456793,
            'callback_query' => [
                'id' => 'callback_456',
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                ],
                'message' => [
                    'message_id' => 6,
                    'chat' => [
                        'id' => 123456789,
                        'type' => 'private',
                    ],
                ],
                'data' => 'snooze:'.$alert->id.':60',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_handles_acknowledge_callback(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $user = User::factory()->create([
            'telegram_id' => '123456789',
        ]);

        $alert = Alert::factory()->create(['user_id' => $user->id]);
        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'user_id' => $user->id,
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456794,
            'callback_query' => [
                'id' => 'callback_789',
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                ],
                'message' => [
                    'message_id' => 7,
                    'chat' => [
                        'id' => 123456789,
                        'type' => 'private',
                    ],
                ],
                'data' => 'ack:'.$history->id,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_handles_contact_share(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456795,
            'message' => [
                'message_id' => 8,
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'date' => time(),
                'contact' => [
                    'phone_number' => '+201234567890',
                    'first_name' => 'Test',
                    'user_id' => 123456789,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_returns_ok_even_on_errors(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        // Send malformed update
        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456796,
            // Missing required message/callback_query structure
        ]);

        // Should still return ok to prevent Telegram retries
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_handles_settings_command(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $user = User::factory()->create([
            'telegram_id' => '123456789',
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456797,
            'message' => [
                'message_id' => 9,
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/settings',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_webhook_handles_language_command(): void
    {
        config(['telebot.bots.horin.webhook.secret_token' => null]);

        $user = User::factory()->create([
            'telegram_id' => '123456789',
        ]);

        $response = $this->postJson(route('telegram.webhook'), [
            'update_id' => 123456798,
            'message' => [
                'message_id' => 10,
                'from' => [
                    'id' => 123456789,
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 123456789,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => '/language',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }
}
