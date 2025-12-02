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
        Schema::create('portfolio_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 28, 8)->default(0);
            $table->decimal('avg_cost', 28, 8)->default(0);
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['portfolio_id', 'asset_id']);
            $table->index(['portfolio_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_assets');
    }
};
