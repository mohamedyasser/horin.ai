<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates materialized views for latest asset prices and predictions
     * with freshness indicators for handling market-closed scenarios.
     */
    public function up(): void
    {
        // Create materialized view for latest asset prices
        DB::statement("
            CREATE MATERIALIZED VIEW mv_latest_asset_prices AS
            SELECT DISTINCT ON (pid)
                pid,
                last AS price,
                pcp AS percent_change,
                pc AS price_change,
                high,
                low,
                last_close,
                turnover_numeric AS volume,
                timestamp,
                to_timestamp(timestamp) AS price_time,
                CASE
                    WHEN timestamp >= EXTRACT(EPOCH FROM (NOW() - INTERVAL '1 hour'))::bigint
                    THEN 'live'
                    WHEN timestamp >= EXTRACT(EPOCH FROM CURRENT_DATE)::bigint
                    THEN 'today'
                    WHEN timestamp >= EXTRACT(EPOCH FROM (CURRENT_DATE - INTERVAL '1 day'))::bigint
                    THEN 'yesterday'
                    ELSE 'older'
                END AS freshness,
                ROUND(EXTRACT(EPOCH FROM (NOW() - to_timestamp(timestamp))) / 3600)::int AS hours_ago
            FROM asset_prices
            ORDER BY pid, timestamp DESC
        ");

        // Create unique index for fast lookups
        DB::statement('CREATE UNIQUE INDEX idx_mv_latest_prices_pid ON mv_latest_asset_prices (pid)');

        // Create materialized view for latest predictions
        DB::statement("
            CREATE MATERIALIZED VIEW mv_latest_predictions AS
            SELECT DISTINCT ON (pid)
                pid,
                symbol,
                model_name,
                price_prediction,
                confidence,
                horizon,
                prediction_time,
                timestamp,
                created_at,
                CASE
                    WHEN timestamp >= EXTRACT(EPOCH FROM CURRENT_DATE)::bigint
                    THEN 'current'
                    WHEN timestamp >= EXTRACT(EPOCH FROM (CURRENT_DATE - INTERVAL '1 day'))::bigint
                    THEN 'yesterday'
                    ELSE 'older'
                END AS freshness,
                (CURRENT_DATE - prediction_time::date)::int AS days_old
            FROM predicted_asset_prices
            ORDER BY pid, timestamp DESC
        ");

        // Create unique index for fast lookups
        DB::statement('CREATE UNIQUE INDEX idx_mv_latest_pred_pid ON mv_latest_predictions (pid)');

        // Create function to refresh both views concurrently
        DB::statement('
            CREATE OR REPLACE FUNCTION refresh_price_views()
            RETURNS void AS $$
            BEGIN
                REFRESH MATERIALIZED VIEW CONCURRENTLY mv_latest_asset_prices;
                REFRESH MATERIALIZED VIEW CONCURRENTLY mv_latest_predictions;
            END;
            $$ LANGUAGE plpgsql
        ');

        // Schedule pg_cron to refresh views every minute
        DB::statement("
            SELECT cron.schedule(
                'refresh_mv_latest_asset_prices_every_minute',
                '*/1 * * * *',
                \$\$SELECT public.refresh_price_views();\$\$
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("SELECT cron.unschedule('refresh_mv_latest_asset_prices_every_minute')");
        DB::statement('DROP FUNCTION IF EXISTS refresh_price_views()');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_latest_predictions');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_latest_asset_prices');
    }
};
