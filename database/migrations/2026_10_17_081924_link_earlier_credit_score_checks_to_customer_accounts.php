<?php

use App\Modules\Customers\Actions\ClaimCreditScoreChecks;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Checks saved before credit_score_checks.customer_id existed have no
     * account, so their owners saw no score and no report in their profile.
     * Each portal account takes back the unlinked checks run with its verified
     * mobile and the PAN on its profile. See ClaimCreditScoreChecks.
     */
    public function up(): void
    {
        $claims = app(ClaimCreditScoreChecks::class);

        Customer::query()
            ->whereNotNull('verified_mobile')
            ->whereNotNull('pan_hash')
            ->each(fn (Customer $customer): int => $claims->forAccount($customer));
    }

    /**
     * Nothing to undo: which checks had no account before is not recorded,
     * and each linked check is one its account's owner ran.
     */
    public function down(): void {}
};
