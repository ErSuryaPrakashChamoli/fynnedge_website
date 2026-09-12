<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the existing contact_enquiries table into the one place every website
 * enquiry lands, rather than standing up a second near-identical table for the
 * Quick Enquiry form. A quick enquiry carries a phone number and nothing else,
 * so name/email/message become nullable and `enquiry_type` is what tells the
 * two apart in the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_enquiries', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->text('message')->nullable()->change();

            $table->string('enquiry_type', 30)->default('contact')->after('message');
            $table->string('source', 40)->default('website')->after('enquiry_type');
            $table->string('status', 20)->default('new')->after('source');

            /*
             * The number of times this same person has enquired. A repeat quick
             * enquiry increments this instead of inserting a duplicate row —
             * the interest is worth recording, a second lead record is not.
             */
            $table->unsignedSmallInteger('enquiry_count')->default(1)->after('status');

            // Unused until an SMS gateway is connected; present so the OTP step can
            // be slotted in front of lead creation without another migration.
            $table->timestamp('phone_verified_at')->nullable()->after('enquiry_count');

            $table->index('phone', 'contact_enquiries_phone_idx');
            $table->index(['enquiry_type', 'status'], 'contact_enquiries_type_status_idx');
        });

        // Everything that existed before this migration came from the full contact
        // form; rows already followed up are closed, the rest are still open.
        DB::table('contact_enquiries')->whereNotNull('handled_at')->update(['status' => 'closed']);
    }

    public function down(): void
    {
        // Quick enquiries have no name/email/message, so the columns cannot go back
        // to NOT NULL while those rows exist — blank them first.
        DB::table('contact_enquiries')->whereNull('name')->update(['name' => '']);
        DB::table('contact_enquiries')->whereNull('email')->update(['email' => '']);
        DB::table('contact_enquiries')->whereNull('message')->update(['message' => '']);

        Schema::table('contact_enquiries', function (Blueprint $table) {
            $table->dropIndex('contact_enquiries_phone_idx');
            $table->dropIndex('contact_enquiries_type_status_idx');

            $table->dropColumn(['enquiry_type', 'source', 'status', 'enquiry_count', 'phone_verified_at']);

            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->text('message')->nullable(false)->change();
        });
    }
};
