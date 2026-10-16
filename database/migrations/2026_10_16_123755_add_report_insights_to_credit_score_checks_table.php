<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The headline figures read from a live check's full credit report (overdue
 * amount, late payments, card usage, enquiries, credit age…), kept so the
 * result page can explain the score and a repeat check inside the reuse
 * period shows the same advice without paying again. Counts and totals only —
 * no lender, account number or identity data — and encrypted at rest (text,
 * for the encrypted:array cast). Null for demo checks and rows from before
 * this change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->text('report_insights')->nullable()->after('raw_response');
        });
    }

    public function down(): void
    {
        Schema::table('credit_score_checks', function (Blueprint $table) {
            $table->dropColumn('report_insights');
        });
    }
};
