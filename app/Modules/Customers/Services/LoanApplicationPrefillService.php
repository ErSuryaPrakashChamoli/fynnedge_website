<?php

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Journey\Models\JourneySession;

/**
 * The values a signed-in customer's own loan application starts with, keyed by
 * journey field key. Only the identity and contact fields the profile holds
 * are offered; loan-specific answers (employment, income, amount, tenure,
 * property, business…) are always asked, never derived — least of all from a
 * credit score.
 *
 * Each value says whether it is verified: the mobile was proved by OTP; name,
 * date of birth and PAN only once staff have verified the customer's identity.
 * Everything else is what the customer told us, shown so they can check it.
 * A prefilled value is only a starting point: the customer sees it in the
 * form and nothing is saved until they submit the step.
 */
class LoanApplicationPrefillService
{
    /**
     * @return array<string, array{value: string, verified: bool}>
     */
    public function forCustomer(Customer $customer): array
    {
        $identityVerified = $customer->isIdentityVerified();

        return collect([
            'full_name' => [$customer->full_name, $identityVerified],
            'phone' => [$customer->verified_mobile, true],
            'email' => [$customer->email, false],
            'date_of_birth' => [$customer->date_of_birth?->toDateString(), $identityVerified],
            'city' => [$customer->city, false],
            'pan_number' => [$customer->pan(), $identityVerified],
        ])
            ->filter(fn (array $pair): bool => filled($pair[0]))
            ->map(fn (array $pair): array => ['value' => (string) $pair[0], 'verified' => $pair[1]])
            ->all();
    }

    /**
     * Prefill for one journey session — only a session that is the customer's
     * own (verified for their mobile, linked to their account), and only for
     * fields the session has not answered yet.
     *
     * @return array<string, array{value: string, verified: bool}>
     */
    public function forSession(JourneySession $session, ?Customer $customer): array
    {
        if ($customer === null || ! $customer->hasPortalAccess() || $session->customer_id !== $customer->id || ! $session->isVerifiedFor($customer)) {
            return [];
        }

        return array_diff_key($this->forCustomer($customer), array_filter($session->responsesByKey(), fn (mixed $value): bool => filled($value)));
    }
}
