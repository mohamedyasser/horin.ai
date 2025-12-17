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
            // Make password nullable for Telegram auth (no password needed)
            $table->string('password')->nullable()->change();

            // Drop phone verification columns (no longer needed with Telegram)
            if (Schema::hasColumn('users', 'phone_verification_code')) {
                $table->dropColumn('phone_verification_code');
            }
        });

        // Separate schema call for second column drop (PostgreSQL compatibility)
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone_verification_expires_at')) {
                $table->dropColumn('phone_verification_expires_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->string('phone_verification_code', 6)->nullable()->after('phone');
            $table->timestamp('phone_verification_expires_at')->nullable()->after('phone_verification_code');
        });
    }
};
