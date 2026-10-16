<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Actions\LogInCustomer;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;

/**
 * Base for the /my-profile pages. Every route runs behind `auth:customer` and
 * EnsureCustomerPortalAccess, and every query below it starts from this
 * customer — records are never looked up by id alone, so another customer's
 * record answers 404, not 403 (its existence is not revealed either).
 */
abstract class PortalController extends Controller
{
    protected function customer(Request $request): Customer
    {
        $customer = $request->user(LogInCustomer::GUARD);

        abort_unless($customer instanceof Customer, 403);

        return $customer;
    }
}
