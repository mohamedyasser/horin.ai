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
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('tv_id')->nullable();
            $table->string('symbol');
            $table->string('isin')->nullable();
            $table->uuid('logo_id')->nullable();
            $table->string('type');
            $table->string('currency');
            $table->string('inv_symbol');
            $table->string('inv_id')->index();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en');
            $table->text('description_ar');
            $table->string('short_description_en');
            $table->string('short_description_ar');
            $table->string('full_name');
            $table->string('mb_url')->nullable();
            $table->smallInteger('status')->default(1);
            $table->foreignUuid('country_id')->nullable()->references('id')->on('countries')->onDelete('set null');
            $table->foreignUuid('market_id')->nullable()->references('id')->on('markets')->onDelete('set null');
            $table->foreignUuid('sector_id')->nullable()->references('id')->on('sectors')->onDelete('set null');
            $table->timestamps();
        });

        Schema::table('markets', function (Blueprint $table) {
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
        });

        Schema::dropIfExists('assets');
    }
};
