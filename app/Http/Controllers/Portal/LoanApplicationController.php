<?php

namespace App\Http\Controllers\Portal;

use App\Models\LoanProduct;
use App\Modules\Customers\Actions\StartPortalLoanApplication;
use App\Modules\Customers\Services\CustomerApplications;
use App\Modules\Customers\Services\LoanApplicationPrefillService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Apply for a Loan and My Loan Applications. Applying enters the existing
 * journey → eligibility → lender → documents → submit workflow (journey.show /
 * applications.show); nothing here re-implements a step of it.
 */
class LoanApplicationController extends PortalController
{
    public function create(Request $request, CustomerApplications $applications, LoanApplicationPrefillService $prefill): View
    {
        $customer = $this->customer($request);
        $drafts = $applications->drafts($customer);

        return view('portal.apply', [
            'customer' => $customer,
            'loanProducts' => $applications->applicableProducts(),
            'draftsByProduct' => $drafts->keyBy('loan_product_id'),
            'prefill' => $prefill->forCustomer($customer),
        ]);
    }

    /**
     * Resumes the customer's unfinished application for this product or starts
     * one. A repeated POST (refresh, double click) lands on the same session.
     */
    public function store(Request $request, LoanProduct $loanProduct, StartPortalLoanApplication $action, CustomerApplications $applications): RedirectResponse
    {
        abort_unless($applications->applicableProducts()->contains($loanProduct), 404);

        $session = $action->handle($this->customer($request), $loanProduct, $request);

        if ($session === null) {
            return redirect()->route('portal.apply')->with('status', __('Applications for this product aren\'t open yet — please check back soon.'));
        }

        return redirect()->route('journey.show', $session);
    }

    public function index(Request $request, CustomerApplications $applications): View
    {
        $customer = $this->customer($request);
        $submitted = $applications->applications($customer)->get();

        return view('portal.applications', [
            'customer' => $customer,
            'drafts' => $applications->drafts($customer),
            'awaitingLender' => $applications->awaitingLenderChoice($customer),
            'applications' => $submitted,
            'missingDocuments' => $applications->missingDocumentCounts($submitted),
        ]);
    }
}
