<?php

namespace App\Modules\Customers\Actions;

use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;

/**
 * Links a person's earlier credit checks to their portal account. A check run
 * before the portal existed (credit_score_checks.customer_id arrived on
 * 2026-10-16), or while the account could not be opened, was saved with no
 * customer. A later check answered from its stored score is a repeat view
 * that the portal does not list, so the customer saw neither the score nor
 * the report in their profile.
 *
 * Only checks that are on no account yet, for the same verified mobile AND
 * the same PAN, are linked: the same proof that already lets this person see
 * that stored score again (SubmitCreditScoreCheck::live()). A check already
 * on an account is never moved.
 */
class ClaimCreditScoreChecks
{
    /**
     * @return int How many checks were linked.
     */
    public function handle(Customer $customer, string $pan, string $mobileNumber): int
    {
        $pan = PanNumber::normalize($pan);

        if (! PanNumber::isValid($pan) || blank($mobileNumber)) {
            return 0;
        }

        return CreditScoreCheck::query()
            ->whereNull('customer_id')
            ->where('mobile_number', $mobileNumber)
            ->where('pan_number', $pan)
            ->update(['customer_id' => $customer->getKey()]);
    }

    /**
     * The same for an account whose verified mobile and PAN are already on
     * its profile, for checks saved before the account existed.
     */
    public function forAccount(Customer $customer): int
    {
        $pan = $customer->pan();

        if (blank($customer->verified_mobile) || $pan === null) {
            return 0;
        }

        return $this->handle($customer, $pan, (string) $customer->verified_mobile);
    }
}
