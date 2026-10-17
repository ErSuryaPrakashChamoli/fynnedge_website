<?php

namespace App\Http\Controllers\Portal;

use App\Modules\CreditScore\Enums\BureauName;
use App\Modules\Customers\Services\CustomerCreditReports;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * My Credit Score: each bureau's latest saved result and whether a refresh is
 * available under the bureau's existing reuse period. The refresh page hosts
 * the same credit-score-check component as the public page (OTP to the
 * registered mobile, fresh consent, the existing reuse/lock/uncertain-hold
 * rules), so a refresh follows exactly the public page's rules — signing in
 * is never taken as consent to a new enquiry.
 */
class CreditScoreOverviewController extends PortalController
{
    public function index(Request $request, CustomerCreditReports $reports): View
    {
        $customer = $this->customer($request);

        return view('portal.credit-score', [
            'customer' => $customer,
            'refresh' => $reports->refreshOverview($customer),
        ]);
    }

    public function refresh(Request $request, BureauName $bureau, CustomerCreditReports $reports): View
    {
        $customer = $this->customer($request);

        return view('portal.credit-score-refresh', [
            'customer' => $customer,
            'bureau' => $bureau,
            'eligibility' => $reports->refreshEligibility($customer, $bureau),
        ]);
    }
}
