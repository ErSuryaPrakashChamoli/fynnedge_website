<?php

namespace App\Http\Middleware;

use App\Modules\Customers\Actions\LogInCustomer;
use App\Modules\Customers\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after `auth:customer` on every /my-profile route.
 *
 *  - An account staff have disabled since it signed in is signed out at its
 *    next request, not when its session happens to expire.
 *  - The default guard goes back to the staff `web` guard (see below).
 *  - Portal pages hold credit and identity data, so the browser must not keep
 *    a copy (back button after sign-out, shared computers) and search engines
 *    must never index them.
 */
class EnsureCustomerPortalAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard(LogInCustomer::GUARD)->user();

        if (! $customer instanceof Customer || ! $customer->hasPortalAccess()) {
            Auth::guard(LogInCustomer::GUARD)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('customer.login')->with('status', __('Access to your profile is switched off. Please contact us to restore it.'));
        }

        // `auth:customer` made the customer guard the request's default. Put the
        // staff guard back: audit rows (audit_logs.user_id → users) and anything
        // else reading Auth::id() must never take a customer's id for a staff
        // user's. Portal code asks for the customer guard by name.
        Auth::shouldUse('web');

        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
