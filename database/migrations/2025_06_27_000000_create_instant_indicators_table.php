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
        Schema::create('instant_indicators', function (Blueprint $table) {
            $table->string('pid')->index();
            $table->bigInteger('timestamp');
            $table->double('rsi')->nullable();
            $table->double('macd_line')->nullable();
            $table->double('macd_signal')->nullable();
            $table->double('macd_histogram')->nullable();
            $table->double('bb_middle')->nullable();
            $table->double('bb_upper')->nullable();
            $table->double('bb_lower')->nullable();
            $table->double('ema')->nullable();
            $table->double('sma')->nullable();
            $table->double('adx')->nullable();
            $table->double('stoch_k')->nullable();
            $table->double('stoch_d')->nullable();
            $table->double('cci')->nullable();
            $table->double('williams_r')->nullable();
            $table->double('roc')->nullable();
            $table->double('momentum')->nullable();
            $table->double('atr')->nullable();
            $table->double('obv')->nullable();
            $table->double('volume_ma')->nullable();
            $table->double('vwap')->nullable();
            $table->double('supertrend')->nullable();
            $table->double('psar')->nullable();
            $table->primary(['pid', 'timestamp']);
        });

        DB::statement("SELECT create_hypertable('instant_indicators', 'timestamp', migrate_data => true)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instant_indicators');
    }
};
