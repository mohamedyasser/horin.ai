<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes password-based authentication columns as the application
     * switches to Telegram-only authentication.
     *
     * WARNING: This is a destructive migration that permanently removes
     * password data. Ensure you have a backup before running.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'phone_verification_code',
                'phone_verification_expires_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * NOTE: Rolling back this migration recreates the columns but cannot
     * restore the data. Existing users will need to re-register or have
     * their passwords reset manually.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->after('phone_verified_at');
            $table->string('phone_verification_code', 6)->nullable()->after('phone');
            $table->timestamp('phone_verification_expires_at')->nullable()->after('phone_verification_code');
        });
    }
};
