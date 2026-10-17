<?php

namespace App\Modules\Customers\Actions;

use App\Models\User;
use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\Customers\Models\Customer;
use App\Modules\MobileOtp\Services\MobileNumber;
use Illuminate\Support\Facades\Cache;

/**
 * The customer a credit-bureau request sent by another system (the external
 * API, routes/external.php) belongs to. That system knows the person, not
 * their FynnEdge id, so it sends their details:
 *
 *  1. an existing customer whose verified mobile (portal login) or contact
 *     phone is this number — one with this PAN on file first, then the portal
 *     account, then the newest record. A customer with a DIFFERENT PAN on file
 *     is never chosen: that is another person sharing the phone.
 *  2. otherwise a new customer with the name, phone, gender and PAN given.
 *
 * It never changes an existing customer's details and never activates a
 * portal account — only an OTP does that (ResolveCustomerAccount).
 */
class MatchCustomerForBureauRequest
{
    private const LOCK_SECONDS = 10;

    public function handle(string $name, string $mobile, string $pan, ?string $gender, User $actor): Customer
    {
        $mobile = MobileNumber::normalize($mobile);
        $pan = PanNumber::normalize($pan);
        $panHash = PanNumber::hash($pan);

        // Two calls for a new person at once must not open two customers.
        return Cache::lock('customer-match:'.$panHash, self::LOCK_SECONDS)->block(
            self::LOCK_SECONDS,
            function () use ($name, $mobile, $pan, $panHash, $gender, $actor): Customer {
                $match = Customer::query()
                    ->where(fn ($query) => $query->where('verified_mobile', $mobile)->orWhere('phone', $mobile))
                    ->where(fn ($query) => $query->whereNull('pan_hash')->orWhere('pan_hash', $panHash))
                    ->orderByRaw('pan_hash is null')
                    ->orderByRaw('verified_mobile is null')
                    ->orderByDesc('id')
                    ->first();

                return $match ?? $this->create($name, $mobile, $pan, $gender, $actor);
            },
        );
    }

    private function create(string $name, string $mobile, string $pan, ?string $gender, User $actor): Customer
    {
        $customer = new Customer([
            'full_name' => trim(preg_replace('/\s+/', ' ', $name) ?? ''),
            'phone' => $mobile,
            'gender' => $gender,
        ]);
        $customer->setPan($pan);
        $customer->save();

        $customer->recordEvent('created_from_external_api', ['source' => 'credit_bureau_request'], $actor->id);

        return $customer;
    }
}
