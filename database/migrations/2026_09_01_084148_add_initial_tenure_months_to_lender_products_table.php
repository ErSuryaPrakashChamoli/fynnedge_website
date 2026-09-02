<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * This lender's configured length, in months, of the "initial" stage of a
     * hybrid/flexi term loan (interest-only repayment) before it converts to
     * standard principal+interest EMI for the rest of the tenure. Nullable
     * and only meaningful for a hybrid-structured product — when unset, the
     * public site falls back to loan_products.default_initial_tenure_months.
     * Subsequent tenure is never stored; it is always
     * (selected total tenure - this value), computed on read.
     */
    public function up(): void
    {
        Schema::table('lender_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('initial_tenure_months')->nullable()->after('max_tenure_months');
        });
    }

    public function down(): void
    {
        Schema::table('lender_products', function (Blueprint $table) {
            $table->dropColumn('initial_tenure_months');
        });
    }
};
