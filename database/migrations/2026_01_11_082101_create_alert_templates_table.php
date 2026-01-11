<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();

            $table->enum('type', ['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation']);
            $table->string('trigger_type');
            $table->json('default_parameters');
            $table->json('default_delivery_config')->nullable();

            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('usage_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_templates');
    }
};
