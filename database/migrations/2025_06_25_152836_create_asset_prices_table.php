<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_prices', function (Blueprint $table) {
            $table->string('pid')->index();
            $table->string('last_dir')->nullable();
            $table->double('last_numeric')->nullable();
            $table->double('last')->nullable();
            $table->double('bid')->nullable();
            $table->double('ask')->nullable();
            $table->double('high')->nullable();
            $table->double('low')->nullable();
            $table->double('last_close')->nullable();
            $table->string('pc')->nullable();
            $table->string('pcp')->nullable();
            $table->string('pc_col')->nullable();
            $table->time('time')->nullable();
            $table->bigInteger('timestamp');
            $table->string('turnover')->nullable();
            $table->double('turnover_numeric')->nullable();
            $table->primary(['pid', 'timestamp']);
        });

        DB::raw("SELECT create_hypertable('asset_prices', 'timestamp', migrate_data => true)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_prices');
    }
};
