<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The generic, category-level calculator limits — the single source of
     * truth LoanCalculatorPreset reads from, admin-editable via
     * LoanProductResource. A specific lender can already state its own
     * narrower amount/tenure/rate range on lender_products (min_amount,
     * max_amount, min_tenure_months, max_tenure_months, interest_rate_from,
     * interest_rate_to already exist there) — these columns are that same
     * shape at the product-category level, so a future per-lender calculator
     * can layer a LenderProduct override on top without restructuring either
     * table.
     */
    public function up(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->decimal('min_amount', 12, 2)->nullable()->after('calculator_key');
            $table->decimal('max_amount', 12, 2)->nullable()->after('min_amount');
            $table->unsignedSmallInteger('min_tenure_months')->nullable()->after('max_amount');
            $table->unsignedSmallInteger('max_tenure_months')->nullable()->after('min_tenure_months');
            $table->decimal('min_interest_rate', 5, 2)->nullable()->after('max_tenure_months');
            $table->decimal('max_interest_rate', 5, 2)->nullable()->after('min_interest_rate');
            // Human-readable indicative range for display (e.g. "10.49% – 24%+") —
            // separate from max_interest_rate, which is deliberately set a bit
            // above the quoted ceiling so a "24%+" range can actually go higher
            // than 24 instead of hard-capping at the display figure.
            $table->string('interest_rate_note')->nullable()->after('max_interest_rate');
        });
    }

    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn([
                'min_amount', 'max_amount', 'min_tenure_months', 'max_tenure_months',
                'min_interest_rate', 'max_interest_rate', 'interest_rate_note',
            ]);
        });
    }
};
