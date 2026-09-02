<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product-level default for the "initial" (interest-only) stage of a
     * hybrid/flexi term loan's two-stage repayment — mirrors the existing
     * default_tenure_months column, which stays the *total* tenure. Only
     * meaningful for a hybrid-structured product (Flexi Hybrid Term Loan);
     * null and unused for every other loan category. A specific lender can
     * override this with its own initial_tenure_months on lender_products,
     * the same generic-product-level-default + per-lender-override pattern
     * already used for amount/tenure/rate.
     */
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_initial_tenure_months')->nullable()->after('default_tenure_months');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn('default_initial_tenure_months');
        });
    }
};
