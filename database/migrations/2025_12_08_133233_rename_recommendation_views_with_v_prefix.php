<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename views to use v_ prefix
        DB::statement('ALTER VIEW latest_recommendations RENAME TO v_latest_recommendations');
        DB::statement('ALTER VIEW latest_detected_signals RENAME TO v_latest_detected_signals');
        DB::statement('ALTER VIEW latest_pattern_detections RENAME TO v_latest_pattern_detections');
        DB::statement('ALTER VIEW latest_anomalies RENAME TO v_latest_anomalies');
        DB::statement('ALTER VIEW latest_signal_classifications RENAME TO v_latest_signal_classifications');
    }

    public function down(): void
    {
        // Rename back to original names
        DB::statement('ALTER VIEW v_latest_recommendations RENAME TO latest_recommendations');
        DB::statement('ALTER VIEW v_latest_detected_signals RENAME TO latest_detected_signals');
        DB::statement('ALTER VIEW v_latest_pattern_detections RENAME TO latest_pattern_detections');
        DB::statement('ALTER VIEW v_latest_anomalies RENAME TO latest_anomalies');
        DB::statement('ALTER VIEW v_latest_signal_classifications RENAME TO latest_signal_classifications');
    }
};
