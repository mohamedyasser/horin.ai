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
        // Make password nullable for Telegram auth (no password needed)
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        // Drop phone verification columns (no longer needed with Telegram)
        if (Schema::hasColumn('users', 'phone_verification_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone_verification_code');
            });
        }

        if (Schema::hasColumn('users', 'phone_verification_expires_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone_verification_expires_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_verification_code', 6)->nullable()->after('phone');
            $table->timestamp('phone_verification_expires_at')->nullable()->after('phone_verification_code');
        });
    }
};
