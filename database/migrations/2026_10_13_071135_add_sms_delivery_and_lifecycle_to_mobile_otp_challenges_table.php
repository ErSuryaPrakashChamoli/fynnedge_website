<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the demo-mode OTP table into the record of a real SMS-backed challenge.
 *
 * Additive only: every existing row is kept. Rows written before this migration
 * were never texted (the code was shown on screen), so they are backfilled with
 * sms_status = not_sent and a status derived from what they already record.
 * `purpose` stays NULL on those rows — which form issued them was never stored,
 * and guessing would put a wrong value in front of an admin.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mobile_otp_challenges', function (Blueprint $table) {
            $table->string('purpose', 40)->nullable()->after('mobile_number');
            $table->string('status', 20)->default('pending')->after('otp_hash');
            $table->unsignedTinyInteger('max_attempts')->default(5)->after('attempts');
            $table->timestamp('consumed_at')->nullable()->after('verified_at');
            $table->string('sms_provider', 40)->nullable()->after('ip_address');
            $table->string('sms_status', 20)->default('pending')->after('sms_provider');
            $table->string('sms_provider_reference', 191)->nullable()->after('sms_status');
            $table->string('sms_failure_reason', 40)->nullable()->after('sms_provider_reference');
            $table->unsignedSmallInteger('sms_http_status')->nullable()->after('sms_failure_reason');
            $table->timestamp('sms_sent_at')->nullable()->after('sms_http_status');

            // Cooldown and supersede lookups: latest challenge for one number + purpose.
            $table->index(['mobile_number', 'purpose', 'created_at'], 'mobile_otp_lookup_index');
            // The admin list filters by status and sorts newest first.
            $table->index(['status', 'created_at'], 'mobile_otp_status_index');
        });

        DB::table('mobile_otp_challenges')->update(['sms_status' => 'not_sent']);
        DB::table('mobile_otp_challenges')->whereNotNull('verified_at')->update(['status' => 'verified']);
        DB::table('mobile_otp_challenges')
            ->whereNull('verified_at')
            ->where('attempts', '>=', 5)
            ->update(['status' => 'exhausted']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobile_otp_challenges', function (Blueprint $table) {
            $table->dropIndex('mobile_otp_lookup_index');
            $table->dropIndex('mobile_otp_status_index');
            $table->dropColumn([
                'purpose',
                'status',
                'max_attempts',
                'consumed_at',
                'sms_provider',
                'sms_status',
                'sms_provider_reference',
                'sms_failure_reason',
                'sms_http_status',
                'sms_sent_at',
            ]);
        });
    }
};
