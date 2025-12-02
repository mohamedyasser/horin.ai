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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('telegram_id')->unique()->nullable();

            $table->enum('language', ['en', 'ar'])->default('ar');
            $table->enum('theme', ['light', 'dark', 'system'])->default('light');
            $table->foreignUuid('country_id')->nullable();
            $table->enum('investment_goal', ['capital_growth', 'fixed_income', 'risk_reduction', 'short_term_speculation', 'retirement_planning', 'wealth_preservation', 'passive_income', 'education_savings', 'home_purchase', 'emergency_fund'])->nullable();
            $table->enum('experience_level', ['beginner', 'intermediate', 'advanced'])->nullable();
            $table->enum('risk_level', ['conservative', 'moderate', 'aggressive'])->nullable();
            $table->enum('trading_style', ['day_trading', 'swing_trading', 'position_trading', 'scalping_trading'])->nullable();

            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
