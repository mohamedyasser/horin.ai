<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Reference data
            CountrySeeder::class,
            SectorSeeder::class,

            // Per-country seeders (market indices, markets, and stock assets)
            SaudiArabiaSeeder::class,
            EgyptSeeder::class,
//            KuwaitSeeder::class,
//            UAESeeder::class,
//            QatarSeeder::class,
//            BahrainSeeder::class,

            // Crypto assets
//            CryptoSeeder::class,

            // Alert templates
            AlertTemplateSeeder::class,

            // Generated data
            UserSeeder::class,
            MarketDataSeeder::class,
            PortfolioSeeder::class,
            AlertSeeder::class,
            NewsSeeder::class,
        ]);
    }
}
