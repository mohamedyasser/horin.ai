<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AlertNotification>
 */
class AlertNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'user_id' => User::factory(),
            'alert_id' => Alert::factory(),
            'alert_history_id' => null,
            'idempotency_key' => Str::uuid(),
            'type' => 'alert.triggered',
            'channel' => fake()->randomElement(['telegram', 'push', 'email', 'in_app']),
            'priority' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'title' => fake()->sentence(3),
            'title_ar' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'body_ar' => fake()->paragraph(),
            'data' => [],
            'status' => 'pending',
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function sent(): static
    {
        return $this->state([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);
    }

    public function read(): static
    {
        return $this->state([
            'status' => 'read',
            'sent_at' => now(),
            'delivered_at' => now(),
            'read_at' => now(),
        ]);
    }

    public function unread(): static
    {
        return $this->state([
            'status' => 'sent',
            'sent_at' => now(),
            'read_at' => null,
        ]);
    }

    public function failed(string $reason = 'Delivery failed'): static
    {
        return $this->state([
            'status' => 'failed',
            'failed_reason' => $reason,
        ]);
    }

    public function telegram(): static
    {
        return $this->state(['channel' => 'telegram']);
    }

    public function push(): static
    {
        return $this->state(['channel' => 'push']);
    }

    public function email(): static
    {
        return $this->state(['channel' => 'email']);
    }

    public function inApp(): static
    {
        return $this->state(['channel' => 'in_app']);
    }

    public function critical(): static
    {
        return $this->state(['priority' => 'critical']);
    }

    public function high(): static
    {
        return $this->state(['priority' => 'high']);
    }

    public function medium(): static
    {
        return $this->state(['priority' => 'medium']);
    }

    public function low(): static
    {
        return $this->state(['priority' => 'low']);
    }
}
