<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        $symbol = strtoupper(fake()->unique()->lexify('????'));

        return [
            'id' => Str::uuid(),
            'symbol' => $symbol,
            'isin' => 'EG'.fake()->numerify('############'),
            'type' => 'stock',
            'currency' => 'EGP',
            'inv_symbol' => $symbol,
            'inv_id' => fake()->numerify('######'),
            'name_en' => fake()->company(),
            'name_ar' => fake()->company(),
            'full_name' => fake()->company().' S.A.E.',
            'status' => 'active',
        ];
    }

    public function crypto(): static
    {
        return $this->state([
            'type' => 'crypto',
            'currency' => 'USD',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
