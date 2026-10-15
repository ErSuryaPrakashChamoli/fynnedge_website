<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens credit_consents into the one consent record for every bureau pull,
 * rather than a second consent table for CRIF reports. Until now a consent
 * could only come from the website journey's checkbox; an employee-initiated
 * CRIF report also needs consent the customer gave by other means (OTP, signed
 * form, recorded call, email), recorded against the customer with the
 * evidence reference and the employee who recorded it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_consents', function (Blueprint $table) {
            $table->unsignedBigInteger('journey_session_id')->nullable()->change();
        });

        Schema::table('credit_consents', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('journey_session_id')->constrained()->nullOnDelete();
            // Every row before this migration came from the journey checkbox.
            $table->string('method', 30)->default('online_journey')->after('purpose');
            $table->string('evidence_reference')->nullable()->after('method');
            $table->foreignId('recorded_by')->nullable()->after('evidence_reference')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Employee-recorded consents have no journey session, so the column cannot
     * go back to NOT NULL while they exist. They only ever authorised CRIF
     * reports, whose table is rolled back before this migration.
     */
    public function down(): void
    {
        DB::table('credit_consents')->whereNull('journey_session_id')->delete();

        Schema::table('credit_consents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['method', 'evidence_reference']);
        });

        Schema::table('credit_consents', function (Blueprint $table) {
            $table->unsignedBigInteger('journey_session_id')->nullable(false)->change();
        });
    }
};
