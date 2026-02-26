<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\User;
use App\Models\UserWishlist;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    /**
     * Seed portfolios, portfolio assets, transactions, and wishlists for users.
     */
    public function run(): void
    {
        $users = User::with('country')->get();
        $allAssets = Asset::where('type', 'stock')->get();

        if ($users->isEmpty() || $allAssets->isEmpty()) {
            $this->command->warn('No users or assets found. Run UserSeeder and AssetSeeder first.');

            return;
        }

        foreach ($users as $user) {
            $this->seedPortfolios($user, $allAssets);
            $this->seedWishlists($user, $allAssets);
        }

        $this->command->info('Seeded portfolios, assets, transactions, and wishlists for '.$users->count().' users.');
    }

    /**
     * Create portfolios with assets and transactions for a user.
     */
    private function seedPortfolios(User $user, mixed $allAssets): void
    {
        // Get assets from user's country, fallback to any assets
        $countryAssets = $allAssets->where('country_id', $user->country_id);
        $availableAssets = $countryAssets->isNotEmpty() ? $countryAssets : $allAssets;

        // Create default portfolio
        $defaultPortfolio = Portfolio::create([
            'user_id' => $user->id,
            'title' => 'Main Portfolio',
            'description' => 'Primary investment portfolio',
            'currency' => $user->country?->currency_code ?? 'USD',
            'is_default' => true,
        ]);

        $this->addAssetsToPortfolio($defaultPortfolio, $availableAssets, rand(3, 7));

        // 40% chance of a secondary portfolio
        if (fake()->boolean(40)) {
            $secondaryPortfolio = Portfolio::create([
                'user_id' => $user->id,
                'title' => fake()->randomElement(['Growth Portfolio', 'Dividend Portfolio', 'Blue Chips', 'Speculative']),
                'description' => fake()->sentence(),
                'currency' => $user->country?->currency_code ?? 'USD',
                'is_default' => false,
            ]);

            $this->addAssetsToPortfolio($secondaryPortfolio, $availableAssets, rand(2, 5));
        }
    }

    /**
     * Add assets and transactions to a portfolio.
     */
    private function addAssetsToPortfolio(Portfolio $portfolio, mixed $availableAssets, int $count): void
    {
        $count = min($count, $availableAssets->count());
        $selectedAssets = $availableAssets->random($count);

        foreach ($selectedAssets as $asset) {
            $quantity = fake()->numberBetween(5, 200);
            $avgCost = fake()->randomFloat(2, 5, 500);

            $portfolioAsset = $portfolio->portfolioAssets()->create([
                'asset_id' => $asset->id,
                'quantity' => $quantity,
                'avg_cost' => $avgCost,
                'risk_score' => fake()->numberBetween(1, 100),
            ]);

            // Create initial buy transaction
            $buyDate = now()->subDays(rand(30, 180));
            $portfolioAsset->transactions()->create([
                'portfolio_id' => $portfolio->id,
                'asset_id' => $asset->id,
                'type' => 'buy',
                'quantity' => $quantity,
                'price' => $avgCost,
                'total_amount' => round($quantity * $avgCost, 2),
                'currency' => $asset->currency ?? $portfolio->currency,
                'fx_rate' => 1.0,
                'transaction_date' => $buyDate,
                'notes' => 'Initial purchase',
            ]);

            // Create 1-4 additional transactions
            $txCount = rand(1, 4);
            for ($i = 0; $i < $txCount; $i++) {
                $isBuy = fake()->boolean(60);
                $txQuantity = fake()->numberBetween(1, max(1, intdiv($quantity, 3)));
                $txPrice = $avgCost * fake()->randomFloat(4, 0.85, 1.2);
                $txDate = $buyDate->copy()->addDays(rand(1, 60));

                $portfolioAsset->transactions()->create([
                    'portfolio_id' => $portfolio->id,
                    'asset_id' => $asset->id,
                    'type' => $isBuy ? 'buy' : 'sell',
                    'quantity' => $txQuantity,
                    'price' => round($txPrice, 2),
                    'total_amount' => round($txQuantity * $txPrice, 2),
                    'currency' => $asset->currency ?? $portfolio->currency,
                    'fx_rate' => 1.0,
                    'transaction_date' => $txDate,
                    'notes' => $isBuy ? 'Additional purchase' : 'Partial sell',
                ]);
            }
        }
    }

    /**
     * Create wishlists (watchlists) for a user.
     */
    private function seedWishlists(User $user, mixed $allAssets): void
    {
        $wishlistCount = rand(2, 8);
        $wishlistAssets = $allAssets->random(min($wishlistCount, $allAssets->count()));

        foreach ($wishlistAssets as $asset) {
            UserWishlist::firstOrCreate([
                'user_id' => $user->id,
                'asset_id' => $asset->id,
            ]);
        }
    }
}
