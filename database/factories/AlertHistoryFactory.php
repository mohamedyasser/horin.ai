<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AlertHistory>
 */
class AlertHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'alert_id' => Alert::factory(),
            'user_id' => User::factory(),
            'asset_id' => Asset::factory(),
            'triggered_at' => now(),
            'trigger_value' => fake()->randomFloat(2, 10, 100),
            'trigger_context' => [
                'change_percent' => fake()->randomFloat(2, -10, 10),
            ],
            'notification_sent' => true,
            'acknowledged_at' => null,
            'escalation_level' => 0,
        ];
    }

    public function acknowledged(): static
    {
        return $this->state([
            'acknowledged_at' => now(),
        ]);
    }

    public function withContext(array $context): static
    {
        return $this->state([
            'trigger_context' => $context,
        ]);
    }

    public function escalated(int $level = 1): static
    {
        return $this->state([
            'escalation_level' => $level,
        ]);
    }
}
