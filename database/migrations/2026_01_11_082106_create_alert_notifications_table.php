<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('alert_history_id')->nullable()->constrained('alert_history')->nullOnDelete();

            $table->string('idempotency_key')->unique();
            $table->string('type'); // Notification class
            $table->enum('channel', ['telegram', 'push', 'email', 'sms', 'in_app']);
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');

            $table->string('title');
            $table->string('title_ar');
            $table->text('body');
            $table->text('body_ar');
            $table->json('data')->nullable(); // Deep linking payload

            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'read'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->string('failed_reason')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('escalated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_notifications');
    }
};
