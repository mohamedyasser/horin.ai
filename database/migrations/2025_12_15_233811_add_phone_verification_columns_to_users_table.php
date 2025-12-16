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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_verification_code', 6)->nullable()->after('phone');
            $table->timestamp('phone_verification_expires_at')->nullable()->after('phone_verification_code');
            $table->timestamp('onboarding_completed_at')->nullable()->after('phone_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_verification_code',
                'phone_verification_expires_at',
                'onboarding_completed_at',
            ]);
        });
    }
};
