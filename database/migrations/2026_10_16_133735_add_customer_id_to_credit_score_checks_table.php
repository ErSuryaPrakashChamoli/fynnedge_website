<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer-portal account a public score check was made for: set only by
 * the check itself (the visitor's OTP-verified mobile resolves the account),
 * never by matching a typed PAN or name. Older checks stay unlinked — they
 * were never tied to a verified account, so they are not shown in anyone's
 * portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('public_id')->constrained()->nullOnDelete();
            $table->index(['customer_id', 'bureau', 'completed_at'], 'credit_score_checks_customer_history_idx');
        });
    }

    public function down(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->dropIndex('credit_score_checks_customer_history_idx');
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
