<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A hybrid/flexi lender's exact repayment structures, as a list of
     * {initial_months, subsequent_months} — e.g. Tata Capital's 12 + 48,
     * 12 + 60, 24 + 60, 24 + 72. Lenders don't keep the subsequent tenure
     * fixed as the total grows, so this replaces the min/max/initial
     * derivation whenever it is set (see LenderProduct::hybridTenureOptions()).
     */
    public function up(): void
    {
        Schema::table('lender_products', function (Blueprint $table) {
            $table->json('hybrid_structures')->nullable()->after('initial_tenure_months');
        });
    }

    public function down(): void
    {
        Schema::table('lender_products', function (Blueprint $table) {
            $table->dropColumn('hybrid_structures');
        });
    }
};
