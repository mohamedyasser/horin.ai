<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_chains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->foreignUuid('trigger_alert_id')->constrained('alerts')->cascadeOnDelete();
            $table->foreignUuid('activate_alert_id')->constrained('alerts')->cascadeOnDelete();

            $table->unsignedInteger('delay_minutes')->default(0);
            $table->unsignedInteger('expires_after_minutes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_chains');
    }
};
