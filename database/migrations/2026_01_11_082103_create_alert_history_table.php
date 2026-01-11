<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // Denormalized
            $table->foreignUuid('asset_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('triggered_at');
            $table->decimal('trigger_value', 20, 6)->nullable();
            $table->json('trigger_context'); // Snapshot of conditions

            $table->boolean('notification_sent')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_history');
    }
};
