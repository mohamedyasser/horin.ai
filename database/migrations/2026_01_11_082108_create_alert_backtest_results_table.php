<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_backtest_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('alert_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('lookback_days');
            $table->unsignedInteger('trigger_count');
            $table->json('triggers'); // Array of trigger events

            $table->decimal('avg_return_1d', 8, 4)->nullable();
            $table->decimal('avg_return_1w', 8, 4)->nullable();
            $table->decimal('avg_return_1m', 8, 4)->nullable();
            $table->decimal('win_rate', 5, 4)->nullable();

            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_backtest_results');
    }
};
