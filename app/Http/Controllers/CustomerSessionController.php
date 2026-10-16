<?php

namespace App\Http\Controllers;

use App\Modules\Customers\Actions\LogInCustomer;
use App\Modules\Customers\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The Existing Customer page (/existing-customer) and signing out. Signing in
 * itself happens in the customer-login Livewire component (mobile → OTP).
 */
class CustomerSessionController extends Controller
{
    public function create(): View
    {
        return view('customer.login');
    }

    /**
     * Ends the customer session: the guard is logged out, the session
     * invalidated and the CSRF token replaced, so a back button or a copied
     * cookie no longer opens the portal.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $customer = $request->user(LogInCustomer::GUARD);

        if ($customer instanceof Customer) {
            $customer->recordEvent('portal_logout');
        }

        Auth::guard(LogInCustomer::GUARD)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login')->with('status', __('You have signed out.'));
    }
}
