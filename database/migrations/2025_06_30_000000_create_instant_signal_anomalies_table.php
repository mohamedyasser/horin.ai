<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('instant_signal_anomalies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('signal_id')->index();
            $table->string('pid')->index();
            $table->string('indicator', 255);
            $table->string('signal_type', 255);
            $table->double('anomaly_score');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('signal_id')
                ->references('id')
                ->on('instant_detected_signals')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instant_signal_anomalies');
    }
};
