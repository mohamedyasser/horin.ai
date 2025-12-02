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
        Schema::create('markets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('country_id')->constrained();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('code');
            $table->string('timezone');
            $table->smallInteger('status')->default(1);
            $table->uuid('asset_id')->nullable();
            $table->string('tv_link')->nullable();
            $table->string('trading_days')->nullable();
            $table->time('open_at')->nullable();
            $table->time('close_at')->nullable();
            $table->boolean('is_open')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
