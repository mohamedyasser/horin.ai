<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the AssetSeeder to seed countries, markets, sectors, and assets
        $this->call([
            CountrySeeder::class,
            SectorSeeder::class,
            AssetSeeder::class,
            CryptoSeeder::class,
        ]);
    }
}
