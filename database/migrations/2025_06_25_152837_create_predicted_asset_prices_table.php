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
        Schema::create('predicted_asset_prices', function (Blueprint $table) {
            $table->string('pid')->index();
            $table->string('symbol');
            $table->string('model_name', 50);
            $table->bigInteger('timestamp');
            $table->timestamp('prediction_time')->useCurrent();
            $table->double('price_prediction');
            $table->double('confidence')->nullable();
            $table->smallInteger('horizon'); // prediction horizon in minutes
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['pid', 'timestamp']);
        });

        DB::raw("SELECT create_hypertable('predicted_asset_prices', 'timestamp', migrate_data => true)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predicted_asset_prices');
    }
};
