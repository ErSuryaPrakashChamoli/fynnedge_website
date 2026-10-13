<?php

use App\Enums\RateStatus;
use App\Models\LenderProduct;
use App\Support\Rates\RateAuditImport;
use App\Support\Rates\RateBook;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Brings the October 2026 rate audit into Catalog → Interest Rates. NOTHING
     * is published: verified primary rates arrive "Awaiting approval" with
     * their official evidence, everything else as drafts. Until a publisher
     * approves them, the site shows "Check current rates" instead of the old,
     * unsourced figures. See App\Support\Rates\RateAuditImport.
     */
    public function up(): void
    {
        if (! LenderProduct::query()->exists()) {
            return;
        }

        Log::info('Imported the October 2026 lender rate audit.', RateAuditImport::run());
    }

    /**
     * Removes only what the import created and nobody has touched since:
     * drafts and still-pending imports. Anything a publisher has published is
     * history and stays.
     */
    public function down(): void
    {
        DB::table('lender_product_rates')
            ->whereIn('status', [RateStatus::Draft->value, RateStatus::PendingApproval->value])
            ->whereNull('created_by')
            ->whereNull('updated_by')
            ->delete();

        RateBook::flush();
    }
};
