<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7 content-consistency audit: LoanProduct already lets an admin
     * override its "Check Your Eligibility" button text via cta_label (the
     * destination route stays fixed) — LoanLandingPage has the identical CTA
     * pattern but no equivalent field, a real inconsistency between two
     * sibling content types with the same button.
     */
    public function up(): void
    {
        Schema::table('loan_landing_pages', function (Blueprint $table) {
            $table->string('cta_label')->nullable()->after('excerpt');
        });
    }

    public function down(): void
    {
        Schema::table('loan_landing_pages', function (Blueprint $table) {
            $table->dropColumn('cta_label');
        });
    }
};
