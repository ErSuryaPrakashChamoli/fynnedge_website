<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns a customer record into a FynnOn customer-portal account without a
 * second identity table. The login identifier is verified_mobile: a number
 * proved by OTP (unique, so one account per verified number). `phone` stays
 * what it always was — a contact number typed into a form or by staff — so the
 * two are never confused.
 *
 * Profile fields are customer-provided; identity_verified_at is set only by
 * staff after KYC and locks the identity fields. The PAN is encrypted (text,
 * for the `encrypted` cast) with a masked copy for display and an HMAC for
 * lookups. Email becomes nullable: an account opened from the credit-score
 * check has a verified mobile but no email yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();

            $table->string('verified_mobile', 10)->nullable()->after('phone')->unique('customers_verified_mobile_unique');
            $table->timestamp('mobile_verified_at')->nullable()->after('verified_mobile');
            $table->timestamp('portal_activated_at')->nullable()->after('mobile_verified_at');
            $table->timestamp('portal_disabled_at')->nullable()->after('portal_activated_at');
            $table->timestamp('last_login_at')->nullable()->after('portal_disabled_at');

            $table->date('date_of_birth')->nullable()->after('last_login_at');
            $table->string('gender', 10)->nullable()->after('date_of_birth');
            $table->text('pan_encrypted')->nullable()->after('gender');
            $table->string('pan_masked', 10)->nullable()->after('pan_encrypted');
            $table->char('pan_hash', 64)->nullable()->after('pan_masked')->index('customers_pan_hash_idx');
            $table->timestamp('identity_verified_at')->nullable()->after('pan_hash');

            $table->string('address_line1')->nullable()->after('identity_verified_at');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city', 100)->nullable()->after('address_line2');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('pincode', 6)->nullable()->after('state');
        });
    }

    /**
     * Email returns to NOT NULL only when every row still has one; a portal
     * account opened without an email would otherwise block the rollback.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_verified_mobile_unique');
            $table->dropIndex('customers_pan_hash_idx');
            $table->dropColumn([
                'verified_mobile', 'mobile_verified_at', 'portal_activated_at', 'portal_disabled_at', 'last_login_at',
                'date_of_birth', 'gender', 'pan_encrypted', 'pan_masked', 'pan_hash', 'identity_verified_at',
                'address_line1', 'address_line2', 'city', 'state', 'pincode',
            ]);
        });

        if (DB::table('customers')->whereNull('email')->doesntExist()) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('email')->nullable(false)->change();
            });
        }
    }
};
