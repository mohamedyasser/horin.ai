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

        // Create materialized view for latest predictions (per pid, module, horizon)
        DB::statement("
            CREATE MATERIALIZED VIEW mv_latest_predictions AS
            SELECT DISTINCT ON (pid, module, horizon)
                pid,
                symbol,
                model_name,
                module,
                horizon,
                horizon_minutes,
                price_prediction,
                confidence,
                lower_bound,
                upper_bound,
                timestamp,
                target_timestamp,
                prediction_time,
                created_at,
                CASE
                    WHEN timestamp >= (EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)::bigint - 3600)
                    THEN 'fresh'
                    WHEN timestamp >= EXTRACT(EPOCH FROM CURRENT_DATE)::bigint
                    THEN 'today'
                    WHEN timestamp >= EXTRACT(EPOCH FROM (CURRENT_DATE - INTERVAL '1 day'))::bigint
                    THEN 'yesterday'
                    ELSE 'older'
                END AS freshness,
                ((EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)::bigint - timestamp) / 60)::int AS age_minutes,
                CASE
                    WHEN target_timestamp IS NOT NULL
                    THEN ((target_timestamp - EXTRACT(EPOCH FROM CURRENT_TIMESTAMP)::bigint) / 60)::int
                    ELSE NULL
                END AS minutes_to_target
            FROM predicted_asset_prices
            WHERE timestamp IS NOT NULL
              AND module IS NOT NULL
              AND horizon IS NOT NULL
            ORDER BY pid, module, horizon, timestamp DESC
        ");

        // Create unique index for fast lookups (composite key)
        DB::statement('CREATE UNIQUE INDEX idx_mv_latest_pred_pid_module_horizon ON mv_latest_predictions (pid, module, horizon)');

        // Create view for latest prediction per asset (single most recent across all horizons)
        DB::statement('
            CREATE VIEW v_latest_prediction_per_asset AS
            SELECT DISTINCT ON (pid)
                pid,
                symbol,
                model_name,
                module,
                horizon,
                horizon_minutes,
                price_prediction,
                confidence,
                lower_bound,
                upper_bound,
                timestamp,
                target_timestamp,
                prediction_time,
                freshness,
                age_minutes
            FROM mv_latest_predictions
            ORDER BY pid, timestamp DESC
        ');

        // Create view for realtime predictions
        DB::statement("
            CREATE VIEW v_realtime_predictions AS
            SELECT
                pid,
                symbol,
                model_name,
                module,
                horizon,
                horizon_minutes,
                price_prediction,
                confidence,
                lower_bound,
                upper_bound,
                timestamp,
                target_timestamp,
                prediction_time,
                created_at,
                freshness,
                age_minutes,
                minutes_to_target
            FROM mv_latest_predictions
            WHERE module = 'realtime'
        ");

        // Create view for short-term predictions
        DB::statement("
            CREATE VIEW v_short_term_predictions AS
            SELECT
                pid,
                symbol,
                model_name,
                module,
                horizon,
                horizon_minutes,
                price_prediction,
                confidence,
                lower_bound,
                upper_bound,
                timestamp,
                target_timestamp,
                prediction_time,
                created_at,
                freshness,
                age_minutes,
                minutes_to_target
            FROM mv_latest_predictions
            WHERE module = 'short_term'
        ");

        // Create view for medium-term predictions
        DB::statement("
            CREATE VIEW v_medium_term_predictions AS
            SELECT
                pid,
                symbol,
                model_name,
                module,
                horizon,
                horizon_minutes,
                price_prediction,
                confidence,
                lower_bound,
                upper_bound,
                timestamp,
                target_timestamp,
                prediction_time,
                created_at,
                freshness,
                age_minutes,
                minutes_to_target
            FROM mv_latest_predictions
            WHERE module = 'medium_term'
        ");

        // Create view for long-term predictions
        DB::statement("
            CREATE VIEW v_long_term_predictions AS
            SELECT
                pid,
                symbol,
                model_name,
                module,
                horizon,
                horizon_minutes,
                price_prediction,
                confidence,
                lower_bound,
                upper_bound,
                timestamp,
                target_timestamp,
                prediction_time,
                created_at,
                freshness,
                age_minutes,
                minutes_to_target
            FROM mv_latest_predictions
            WHERE module = 'long_term'
        ");

        // Create view for prediction summary (pivot table by horizon)
        DB::statement("
            CREATE VIEW v_prediction_summary AS
            SELECT
                pid,
                symbol,
                MAX(CASE WHEN horizon = '2min' THEN price_prediction END) AS pred_2min,
                MAX(CASE WHEN horizon = '5min' THEN price_prediction END) AS pred_5min,
                MAX(CASE WHEN horizon = '15min' THEN price_prediction END) AS pred_15min,
                MAX(CASE WHEN horizon = '1hour' THEN price_prediction END) AS pred_1hour,
                MAX(CASE WHEN horizon = '1day' THEN price_prediction END) AS pred_1day,
                MAX(CASE WHEN horizon = '1week' THEN price_prediction END) AS pred_1week,
                MAX(CASE WHEN horizon = '1month' THEN price_prediction END) AS pred_1month,
                MAX(CASE WHEN horizon = '3month' THEN price_prediction END) AS pred_3month,
                MAX(CASE WHEN horizon = '6month' THEN price_prediction END) AS pred_6month,
                MAX(CASE WHEN horizon = '1year' THEN price_prediction END) AS pred_1year,
                MAX(timestamp) AS latest_update,
                MAX(prediction_time) AS latest_prediction_time
            FROM mv_latest_predictions
            GROUP BY pid, symbol
        ");

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

        // Drop views first (they depend on materialized views)
        DB::statement('DROP VIEW IF EXISTS v_prediction_summary');
        DB::statement('DROP VIEW IF EXISTS v_long_term_predictions');
        DB::statement('DROP VIEW IF EXISTS v_medium_term_predictions');
        DB::statement('DROP VIEW IF EXISTS v_short_term_predictions');
        DB::statement('DROP VIEW IF EXISTS v_realtime_predictions');
        DB::statement('DROP VIEW IF EXISTS v_latest_prediction_per_asset');

        // Then drop materialized views
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_latest_predictions');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_latest_asset_prices');
    }
};
