<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a check was paid for once paid refreshes are switched on: free, plan,
 * wallet (with its ledger row), reused (answered from a stored score, no
 * charge). Null for checks made while billing is off and for every check made
 * before this change — those count as the customer's free check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->string('billing', 20)->nullable()->after('status');
            $table->foreignId('wallet_transaction_id')->nullable()->after('billing')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_transaction_id');
            $table->dropColumn('billing');
        });
    }
};
