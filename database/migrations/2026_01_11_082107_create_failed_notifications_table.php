<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notification_id')->nullable()->constrained('alert_notifications')->nullOnDelete();
            $table->foreignUuid('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->text('error');
            $table->json('payload');
            $table->timestamp('failed_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('should_retry')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_notifications');
    }
};
