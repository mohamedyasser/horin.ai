<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Latest recommendation per asset
        DB::statement("
            CREATE OR REPLACE VIEW latest_recommendations AS
            SELECT DISTINCT ON (pid)
                id,
                pid,
                score,
                recommendation,
                created_at
            FROM instant_recommendations
            ORDER BY pid, created_at DESC
        ");

        // Active signals per asset (last 30 minutes)
        DB::statement("
            CREATE OR REPLACE VIEW latest_detected_signals AS
            SELECT
                id,
                pid,
                timestamp,
                indicator,
                signal_type,
                value,
                strength,
                created_at
            FROM instant_detected_signals
            WHERE created_at >= NOW() - INTERVAL '30 minutes'
            ORDER BY pid, strength DESC
        ");

        // Latest pattern detection per asset
        DB::statement("
            CREATE OR REPLACE VIEW latest_pattern_detections AS
            SELECT DISTINCT ON (pid)
                pid,
                timestamp,
                patterns,
                has_head_shoulder,
                has_multiple_tops_bottoms,
                has_triangle,
                has_wedge,
                has_channel,
                has_double_top_bottom,
                has_trendline,
                has_support_resistance,
                has_pivots,
                pattern_count,
                created_at
            FROM instant_pattern_detections
            ORDER BY pid, timestamp DESC
        ");

        // Active anomalies (last 30 minutes)
        DB::statement("
            CREATE OR REPLACE VIEW latest_anomalies AS
            SELECT
                id,
                symbol,
                anomaly_type,
                confidence_score,
                detected_at,
                'window',
                price,
                volume,
                extra
            FROM instant_anomalies
            WHERE detected_at >= NOW() - INTERVAL '30 minutes'
            ORDER BY symbol, detected_at DESC
        ");

        // Latest signal classification per asset
        DB::statement("
            CREATE OR REPLACE VIEW latest_signal_classifications AS
            SELECT DISTINCT ON (pid)
                id,
                pid,
                signal_id,
                classification,
                confidence,
                metadata,
                created_at
            FROM instant_signal_classifications
            ORDER BY pid, created_at DESC
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS latest_recommendations');
        DB::statement('DROP VIEW IF EXISTS latest_detected_signals');
        DB::statement('DROP VIEW IF EXISTS latest_pattern_detections');
        DB::statement('DROP VIEW IF EXISTS latest_anomalies');
        DB::statement('DROP VIEW IF EXISTS latest_signal_classifications');
    }
};
