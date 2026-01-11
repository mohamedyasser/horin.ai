<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('alert_templates')->nullOnDelete();
            $table->uuid('parent_alert_id')->nullable();
            $table->uuid('chain_from_id')->nullable();

            // Alert type configuration
            $table->enum('type', ['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation']);
            $table->string('trigger_type'); // target_price, breakout, zone, etc.
            $table->enum('scope', ['single_asset', 'watchlist', 'portfolio', 'sector', 'market'])->default('single_asset');
            $table->enum('direction', ['above', 'below', 'both', 'cross_up', 'cross_down'])->nullable();
            $table->enum('condition_logic', ['single', 'and', 'or'])->default('single');
            $table->json('parameters'); // Type-specific configuration

            // Status & lifecycle
            $table->enum('status', ['active', 'triggered', 'paused', 'expired', 'chained', 'deleted'])->default('active');
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->boolean('is_recurring')->default(false);
            $table->unsignedInteger('cooldown_minutes')->default(60);
            $table->unsignedInteger('max_triggers')->nullable();
            $table->unsignedInteger('triggered_count')->default(0);

            // Delivery configuration
            $table->json('delivery_config')->nullable();
            $table->json('escalation_config')->nullable();

            // Timing
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('market_hours_only')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        // Add self-referential foreign keys after table creation
        Schema::table('alerts', function (Blueprint $table) {
            $table->foreign('parent_alert_id')->references('id')->on('alerts')->nullOnDelete();
            $table->foreign('chain_from_id')->references('id')->on('alerts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropForeign(['parent_alert_id']);
            $table->dropForeign(['chain_from_id']);
        });

        Schema::dropIfExists('alerts');
    }
};
