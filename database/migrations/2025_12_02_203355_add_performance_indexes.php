<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Predictions indexes for efficient queries
        DB::statement('CREATE INDEX IF NOT EXISTS predicted_asset_prices_pid_timestamp_idx ON predicted_asset_prices (pid, timestamp DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS predicted_asset_prices_horizon_timestamp_idx ON predicted_asset_prices (horizon, timestamp DESC)');
        DB::statement('CREATE INDEX IF NOT EXISTS predicted_asset_prices_confidence_idx ON predicted_asset_prices (confidence)');

        // Asset prices index for latest price queries
        DB::statement('CREATE INDEX IF NOT EXISTS asset_prices_pid_timestamp_idx ON asset_prices (pid, timestamp DESC)');

        // Assets foreign key indexes (for whereHas queries)
        DB::statement('CREATE INDEX IF NOT EXISTS assets_market_id_idx ON assets (market_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS assets_sector_id_idx ON assets (sector_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS predicted_asset_prices_pid_timestamp_idx');
        DB::statement('DROP INDEX IF EXISTS predicted_asset_prices_horizon_timestamp_idx');
        DB::statement('DROP INDEX IF EXISTS predicted_asset_prices_confidence_idx');
        DB::statement('DROP INDEX IF EXISTS asset_prices_pid_timestamp_idx');
        DB::statement('DROP INDEX IF EXISTS assets_market_id_idx');
        DB::statement('DROP INDEX IF EXISTS assets_sector_id_idx');
    }
};
