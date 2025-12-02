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
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('content')->nullable();
            $table->text('description')->nullable();
            $table->text('link');
            $table->string('resource_id')->unique();
            $table->string('language');
            $table->string('image_url')->nullable();
            $table->string('small_image_url')->nullable();
            $table->smallInteger('score')->nullable();
            $table->text('sentiment')->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('negative_aspects')->nullable();
            $table->jsonb('positive_aspects')->nullable();
            $table->uuid('asset_id')->nullable();
            $table->uuid('market_id')->nullable();
            $table->uuid('country_id')->nullable();
            $table->json('images')->nullable();
            $table->string('category')->nullable();
            $table->timestamp('date')->nullable();
            $table->uuid('sector_id')->nullable();
            $table->boolean('is_rewritten')->default(false);
            $table->string('source');
            $table->jsonb('actions')->nullable();
            $table->string('slug')->nullable();
            $table->jsonb('meta_tags')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();

            $table->index(['language', 'created_at']);
            $table->index(['language', 'is_rewritten', 'image_url', 'created_at']);
            $table->index(['asset_id', 'created_at']);
            $table->index(['asset_id', 'language', 'created_at']);
            $table->unique(['resource_id', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
