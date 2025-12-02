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
        Schema::create('portfolio_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('portfolio_asset_id')->constrained('portfolio_assets')->cascadeOnDelete();
            $table->enum('type', ['buy', 'sell']);
            $table->decimal('quantity', 28, 8);
            $table->decimal('price', 28, 8);
            $table->decimal('total_amount', 28, 8);

            $table->char('currency', 3);
            $table->decimal('fx_rate', 18, 10)->default(1);

            $table->timestamp('transaction_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'transaction_date']);
            $table->index(['portfolio_id', 'asset_id']);
            $table->index(['portfolio_id', 'type', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_transactions');
    }
};
