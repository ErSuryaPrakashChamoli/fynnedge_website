<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one place a verified mobile number becomes a customer-portal account.
 * Callers must have proved the number with an OTP in this same request (the
 * credit-score check, the /existing-customer login); a typed-in PAN, name or
 * email never selects an account.
 *
 * Which record a verified number maps to:
 *  1. the account whose verified_mobile it already is;
 *  2. otherwise exactly one existing, unclaimed customer record whose contact
 *     phone is that number — the journey only stores an OTP-verified phone and
 *     staff enter the rest — which is activated rather than duplicated;
 *  3. otherwise (no record, or several records share the phone so there is no
 *     safe choice) a new account — only when the caller may open one.
 *
 * A unique index on verified_mobile settles two simultaneous first checks: the
 * loser re-reads the winner's account.
 */
class ResolveCustomerAccount
{
    /**
     * For a flow that may open a new account (the credit-score check).
     */
    public function forVerifiedMobile(string $mobile): Customer
    {
        return $this->resolve($mobile, mayCreate: true)
            ?? throw new InvalidArgumentException('An account could not be opened for this number.');
    }

    /**
     * For signing in: an existing account, or a record that can be activated.
     * Never creates one — the login page is for existing customers.
     */
    public function forLogin(string $mobile): ?Customer
    {
        return $this->resolve($mobile, mayCreate: false);
    }

    private function resolve(string $mobile, bool $mayCreate): ?Customer
    {
        $mobile = MobileNumber::normalize($mobile);

        if (! MobileNumber::isValid($mobile)) {
            throw new InvalidArgumentException('ResolveCustomerAccount expects a valid 10-digit mobile number.');
        }

        try {
            return DB::transaction(fn (): ?Customer => $this->resolveLocked($mobile, $mayCreate));
        } catch (UniqueConstraintViolationException) {
            return Customer::query()->where('verified_mobile', $mobile)->first();
        }
    }

    private function resolveLocked(string $mobile, bool $mayCreate): ?Customer
    {
        $account = Customer::query()->where('verified_mobile', $mobile)->lockForUpdate()->first();

        if ($account !== null) {
            return $account;
        }

        $candidates = Customer::query()
            ->where('phone', $mobile)
            ->whereNull('verified_mobile')
            ->lockForUpdate()
            ->limit(2)
            ->get();

        if ($candidates->count() === 1) {
            $customer = $candidates->first();
            $customer->forceFill([
                'verified_mobile' => $mobile,
                'mobile_verified_at' => now(),
                'portal_activated_at' => now(),
            ])->save();
            $customer->recordEvent('portal_account_activated', ['source' => 'existing_record']);

            return $customer;
        }

        if (! $mayCreate) {
            return null;
        }

        $customer = Customer::query()->create([
            'phone' => $mobile,
            'verified_mobile' => $mobile,
            'mobile_verified_at' => now(),
            'portal_activated_at' => now(),
        ]);
        $customer->recordEvent('portal_account_created', ['source' => 'credit_score_check', 'shared_phone_records' => $candidates->count()]);

        return $customer;
    }
}
