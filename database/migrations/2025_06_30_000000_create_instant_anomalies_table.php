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
        Schema::create('instant_anomalies', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('symbol', 10)->index();
            $table->string('anomaly_type', 50);
            $table->float('confidence_score')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->string('window', 10)->nullable();
            $table->decimal('price', 20, 8)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->jsonb('extra')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instant_anomalies');
    }
};
