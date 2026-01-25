<?php

namespace Database\Factories;

use App\Models\AssetNew;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetNew>
 */
class AssetNewFactory extends Factory
{
    protected $model = AssetNew::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'description' => fake()->paragraph(),
            'content' => fake()->paragraphs(3, true),
            'image_url' => null,
            'score' => fake()->numberBetween(1, 10),
            'sentiment' => fake()->randomElement(['positive', 'negative', 'neutral']),
            'action' => fake()->randomElement(['buy', 'sell', 'watch']),
            'category' => fake()->randomElement(['Market Analysis', 'Company News', 'Economic Update', 'Sector Report']),
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
            'is_rewritten' => true,
            'risks' => fake()->randomElements([
                'Market volatility risk',
                'Regulatory changes',
                'Competition increase',
                'Economic downturn',
            ], fake()->numberBetween(0, 3)),
            'opportunities' => fake()->randomElements([
                'Market expansion',
                'New product launch',
                'Strategic partnerships',
                'Cost reduction initiatives',
            ], fake()->numberBetween(0, 3)),
            'affected_sectors' => fake()->randomElements([
                'Banking',
                'Energy',
                'Real Estate',
                'Technology',
            ], fake()->numberBetween(0, 2)),
            'meta_tags' => fake()->randomElements([
                'stocks',
                'investing',
                'market',
                'analysis',
            ], fake()->numberBetween(1, 3)),
            'meta_description' => fake()->sentence(),
        ];
    }

    /**
     * Indicate that the news is published (visible on frontend).
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_rewritten' => true,
            'image_url' => 'news/'.fake()->uuid().'.jpg',
        ]);
    }

    /**
     * Indicate that the news is positive.
     */
    public function positive(): static
    {
        return $this->state(fn (array $attributes) => [
            'sentiment' => 'positive',
            'action' => 'buy',
            'score' => fake()->numberBetween(7, 10),
        ]);
    }

    /**
     * Indicate that the news is negative.
     */
    public function negative(): static
    {
        return $this->state(fn (array $attributes) => [
            'sentiment' => 'negative',
            'action' => 'sell',
            'score' => fake()->numberBetween(1, 4),
        ]);
    }

    /**
     * Indicate that the news is neutral.
     */
    public function neutral(): static
    {
        return $this->state(fn (array $attributes) => [
            'sentiment' => 'neutral',
            'action' => 'watch',
            'score' => fake()->numberBetween(4, 6),
        ]);
    }
}
