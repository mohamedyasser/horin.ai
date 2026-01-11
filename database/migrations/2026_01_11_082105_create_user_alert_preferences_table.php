<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_alert_preferences', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();

            $table->json('default_channels')->default('["telegram", "in_app"]');
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('timezone')->default('Africa/Cairo');

            $table->unsignedInteger('max_alerts_per_hour')->default(10);
            $table->unsignedInteger('max_alerts_per_day')->default(50);

            $table->boolean('digest_enabled')->default(false);
            $table->time('digest_time')->default('20:00');
            $table->boolean('smart_defaults_enabled')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_alert_preferences');
    }
};
