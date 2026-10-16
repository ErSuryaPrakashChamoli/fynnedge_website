<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\Auth;

/**
 * Starts a customer-portal session for an account whose mobile was just proved
 * by OTP. The session id is regenerated (no fixation) and the login recorded.
 * Signing in never starts a credit-bureau enquiry — it only opens what is
 * already saved.
 */
class LogInCustomer
{
    public const GUARD = 'customer';

    /**
     * @return bool False when staff have disabled the account; nothing changes then.
     */
    public function handle(Customer $customer, string $via): bool
    {
        if (! $customer->hasPortalAccess()) {
            $customer->recordEvent('portal_login_refused', ['via' => $via, 'reason' => 'account_disabled']);

            return false;
        }

        Auth::guard(self::GUARD)->login($customer);
        session()->regenerate();

        $customer->forceFill(['last_login_at' => now()])->save();
        $customer->recordEvent('portal_login', ['via' => $via]);

        return true;
    }
}
