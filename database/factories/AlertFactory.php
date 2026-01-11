<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Alert>
 */
class AlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'asset_id' => Asset::factory(),
            'type' => 'price',
            'trigger_type' => 'target_price',
            'scope' => 'single_asset',
            'direction' => 'above',
            'condition_logic' => 'single',
            'parameters' => [
                'target_price' => fake()->randomFloat(2, 10, 100),
            ],
            'status' => 'active',
            'priority' => 'medium',
            'is_recurring' => false,
            'cooldown_minutes' => 60,
            'market_hours_only' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function triggered(): static
    {
        return $this->state([
            'status' => 'triggered',
            'last_triggered_at' => now(),
            'triggered_count' => 1,
        ]);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }

    public function snoozed(): static
    {
        return $this->state([
            'snoozed_until' => now()->addHours(2),
        ]);
    }

    public function recurring(): static
    {
        return $this->state(['is_recurring' => true]);
    }

    public function targetPrice(float $price, string $direction = 'above'): static
    {
        return $this->state([
            'type' => 'price',
            'trigger_type' => 'target_price',
            'direction' => $direction,
            'parameters' => [
                'target_price' => $price,
            ],
        ]);
    }

    public function breakout(float $level, string $direction = 'above'): static
    {
        return $this->state([
            'type' => 'price',
            'trigger_type' => 'breakout',
            'direction' => $direction,
            'parameters' => [
                'level' => $level,
                'confirmation' => 'sustained',
                'confirmation_seconds' => 30,
            ],
        ]);
    }

    public function dailyChange(float $thresholdPercent): static
    {
        return $this->state([
            'type' => 'price',
            'trigger_type' => 'daily_change',
            'direction' => 'both',
            'parameters' => [
                'threshold_percent' => $thresholdPercent,
                'from_reference' => 'open',
            ],
        ]);
    }

    public function signal(): static
    {
        return $this->state([
            'type' => 'signal',
            'trigger_type' => 'signal',
            'parameters' => [
                'indicators' => ['RSI', 'MACD'],
                'signal_types' => ['oversold', 'bullish_cross'],
                'min_strength' => 0.7,
                'any_or_all' => 'any',
            ],
        ]);
    }

    public function prediction(): static
    {
        return $this->state([
            'type' => 'prediction',
            'trigger_type' => 'prediction',
            'parameters' => [
                'prediction_type' => 'price_direction',
                'horizon' => '1hour',
                'direction' => 'up',
                'min_confidence' => 0.75,
            ],
        ]);
    }

    public function anomaly(): static
    {
        return $this->state([
            'type' => 'anomaly',
            'trigger_type' => 'anomaly',
            'parameters' => [
                'anomaly_types' => ['price_spike', 'volume_surge'],
                'min_confidence' => 0.8,
                'severity' => ['high', 'critical'],
            ],
        ]);
    }

    public function pattern(): static
    {
        return $this->state([
            'type' => 'pattern',
            'trigger_type' => 'pattern',
            'parameters' => [
                'patterns' => ['double_bottom', 'triangle'],
                'pattern_status' => 'confirmed',
                'min_confidence' => 0.7,
            ],
        ]);
    }

    public function recommendation(): static
    {
        return $this->state([
            'type' => 'recommendation',
            'trigger_type' => 'recommendation',
            'parameters' => [
                'trigger_on' => 'change',
                'recommendations' => ['strong_buy', 'buy'],
                'min_score' => 0.75,
            ],
        ]);
    }

    public function watchlistScope(): static
    {
        return $this->state([
            'asset_id' => null,
            'scope' => 'watchlist',
        ]);
    }

    public function portfolioScope(): static
    {
        return $this->state([
            'asset_id' => null,
            'scope' => 'portfolio',
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(['priority' => 'high']);
    }

    public function criticalPriority(): static
    {
        return $this->state(['priority' => 'critical']);
    }

    public function withMaxTriggers(int $max): static
    {
        return $this->state(['max_triggers' => $max]);
    }

    public function withCooldown(int $minutes): static
    {
        return $this->state(['cooldown_minutes' => $minutes]);
    }

    public function expiresIn(int $days): static
    {
        return $this->state(['expires_at' => now()->addDays($days)]);
    }
}
