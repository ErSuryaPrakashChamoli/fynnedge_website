<?php

use App\Models\LenderProduct;
use App\Support\Rates\RateAuditImport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Re-runs the October 2026 rate audit import now that it matches audit
     * records by loan category instead of the loan product's slug. Production's
     * personal loan product is "Personal Loans" at /loans/personal-loans, so the
     * first run (2026_10_13_123020) matched none of its lenders and queued no
     * personal loan rates for approval — the product showed no rates at all.
     *
     * Only offers with no rates, or with nothing but the untouched pre-audit
     * placeholders the first run left, are touched; anything an editor has
     * edited, submitted or published is left alone. As before, nothing is
     * published: the audited rates arrive "Awaiting approval".
     */
    public function up(): void
    {
        if (! LenderProduct::query()->exists()) {
            return;
        }

        Log::info('Re-imported the October 2026 lender rate audit by loan type.', RateAuditImport::run());
    }

    /**
     * Data-only. The first import's down() removes untouched imported rows.
     */
    public function down(): void {}
};
