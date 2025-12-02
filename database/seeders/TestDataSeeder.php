<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // get all users
        $users = User::with([
            'country',
            'portfolios',
        ])->get();

        $assets = Asset::all();

        foreach ($users as $user) {
            if ($user->portfolios->isEmpty()) {
                $user->portfolios()->create([
                    'title' => 'Default Portfolio',
                    'currency' => $user->country->currency_code ?? 'USD',
                    'is_default' => true,
                ]);
            }
            foreach ($user->portfolios as $portfolio) {
                // assign 5 random assets to each portfolio
                $randomAssets = $assets
                    ->where('country_id', $user->country_id)
                    ->where('type', 'stock')
                    ->whereNotNull('currency')->random(5);

                foreach ($randomAssets as $asset) {
                    // portfolioAsset needs for creation 'quantity', 'average_buy_price', 'cost', 'current_price', 'target_allocation',
                    $portfolioAsset = $portfolio->portfolioAssets()->createOrFirst([
                        'asset_id' => $asset->id,
                    ], [
                        'quantity' => rand(1, 100),
                        'avg_cost' => rand(10, 1000) / 10,
                    ]);

                    // create a transaction for the portfolio asset

                    // transaction needs for creation 'type', 'quantity', 'price', 'total_amount', 'transaction_date', 'notes',
                    $portfolioAsset->transactions()->create([
                        'portfolio_id' => $portfolio->id,
                        'asset_id' => $portfolioAsset->asset_id,
                        'type' => 'buy',
                        'quantity' => rand(1, 10),
                        'price' => rand(10, 1000) / 10,
                        'total_amount' => rand(10, 1000) / 10,
                        'transaction_date' => now()->subDays(rand(1, 30)),
                        'currency' => $asset->currency,
                    ]);

                    // create 3 random transactions for each portfolio asset
                    for ($i = 0; $i < 5; $i++) {
                        $portfolioAsset->transactions()->create([
                            'portfolio_id' => $portfolio->id,
                            'asset_id' => $portfolioAsset->asset_id,
                            'type' => 'sell',
                            'quantity' => rand(1, 10),
                            'price' => rand(10, 1000) / 10,
                            'total_amount' => rand(10, 1000) / 10,
                            'transaction_date' => now()->subDays(rand(1, 30)),
                            'currency' => $asset->currency,
                        ]);
                    }
                }
            }
        }
    }
}
