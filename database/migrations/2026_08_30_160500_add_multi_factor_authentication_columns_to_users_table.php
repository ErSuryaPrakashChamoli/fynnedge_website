<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filament's built-in TOTP app-authentication MFA (filament/filament, no
     * extra package) — opt-in per admin via their own Profile page, never
     * forced (panel's multiFactorAuthentication() call omits isRequired, which
     * defaults to false). Existing admins keep signing in exactly as before
     * until they choose to enrol. Recovery codes cover lost-device recovery.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
