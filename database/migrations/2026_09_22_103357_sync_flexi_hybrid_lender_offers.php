<?php

use App\Models\LoanProduct;
use App\Support\Calculators\FlexiHybridLenderTerms;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Brings an existing database's Flexi Hybrid Term Loan lender offers in
     * line with the researched terms in FlexiHybridLenderTerms: Piramal
     * Finance replaces Poonawalla Fincorp, and every offer gets real rates and
     * the lender's own initial + subsequent structures, so the public
     * comparison never falls back to "Available on request". A no-op on a
     * fresh database, where FlexiHybridTermLoanSeeder does the same.
     */
    public function up(): void
    {
        $product = LoanProduct::query()->where('slug', 'flexi-hybrid-term-loan')->first();

        if ($product) {
            FlexiHybridLenderTerms::sync($product);
        }
    }

    /**
     * Data-only; the previous admin-entered figures are not restorable.
     */
    public function down(): void {}
};
