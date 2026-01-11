<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alerts table indexes
        Schema::table('alerts', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_alerts_user_status');
            $table->index(['type', 'status'], 'idx_alerts_type_status');
        });

        // Partial indexes (PostgreSQL specific)
        DB::statement('CREATE INDEX idx_alerts_asset_type_active ON alerts(asset_id, type, status) WHERE status = \'active\'');
        DB::statement('CREATE INDEX idx_alerts_price_active ON alerts(asset_id, trigger_type) WHERE type = \'price\' AND status = \'active\'');
        DB::statement('CREATE INDEX idx_alerts_expires ON alerts(expires_at) WHERE expires_at IS NOT NULL AND status = \'active\'');
        DB::statement('CREATE INDEX idx_alerts_snoozed ON alerts(snoozed_until) WHERE snoozed_until IS NOT NULL');

        // JSON path index for target_price lookups
        DB::statement('CREATE INDEX idx_alerts_target_price ON alerts USING gin ((parameters->\'target_price\'))');

        // Alert history indexes
        Schema::table('alert_history', function (Blueprint $table) {
            $table->index(['user_id', 'triggered_at'], 'idx_history_user_triggered');
            $table->index(['alert_id', 'triggered_at'], 'idx_history_alert');
            $table->index(['asset_id', 'triggered_at'], 'idx_history_asset');
        });

        // Notifications indexes
        Schema::table('alert_notifications', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'created_at'], 'idx_notifications_user_status');
        });

        DB::statement('CREATE INDEX idx_notifications_scheduled ON alert_notifications(scheduled_at) WHERE status = \'pending\'');
        DB::statement('CREATE INDEX idx_notifications_escalation ON alert_notifications(escalation_level, escalated_at) WHERE escalation_level > 0');
    }

    public function down(): void
    {
        // Drop partial indexes
        DB::statement('DROP INDEX IF EXISTS idx_alerts_asset_type_active');
        DB::statement('DROP INDEX IF EXISTS idx_alerts_price_active');
        DB::statement('DROP INDEX IF EXISTS idx_alerts_expires');
        DB::statement('DROP INDEX IF EXISTS idx_alerts_snoozed');
        DB::statement('DROP INDEX IF EXISTS idx_alerts_target_price');
        DB::statement('DROP INDEX IF EXISTS idx_notifications_scheduled');
        DB::statement('DROP INDEX IF EXISTS idx_notifications_escalation');

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('idx_alerts_user_status');
            $table->dropIndex('idx_alerts_type_status');
        });

        Schema::table('alert_history', function (Blueprint $table) {
            $table->dropIndex('idx_history_user_triggered');
            $table->dropIndex('idx_history_alert');
            $table->dropIndex('idx_history_asset');
        });

        Schema::table('alert_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_status');
        });
    }
};
