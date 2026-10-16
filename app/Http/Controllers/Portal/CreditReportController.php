<?php

namespace App\Http\Controllers\Portal;

use App\Modules\Cibil\Models\CibilCreditRequest;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CreditReportView;
use App\Modules\Customers\Services\CustomerCreditReports;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The customer's own saved reports. Every lookup goes through
 * CustomerCreditReports::find(), scoped to the signed-in customer and to
 * genuine checks, so another customer's report — or a demo one — is a 404.
 * Nothing here calls a bureau: viewing a report or its PDF only reads what the
 * check already stored.
 */
class CreditReportController extends PortalController
{
    public function index(Request $request, CustomerCreditReports $reports): View
    {
        $customer = $this->customer($request);

        return view('portal.credit-reports.index', [
            'customer' => $customer,
            'reports' => $reports->history($customer)->paginate(15),
        ]);
    }

    public function show(Request $request, string $report, CustomerCreditReports $reports): View
    {
        $customer = $this->customer($request);
        $check = $reports->find($customer, $report) ?? abort(404);
        $view = CreditReportView::for($check);

        if ($view->request !== null) {
            $this->logCustomerAccess($view->request, 'viewed_by_customer', $check, $customer);
        }

        return view('portal.credit-reports.show', [
            'customer' => $customer,
            'check' => $check,
            'report' => $view,
        ]);
    }

    /**
     * Serves a PDF only when the bureau request already holds an encrypted copy
     * on the private disk. A provider's temporary link is never fetched,
     * followed or shown from here — buying or downloading a PDF stays a staff
     * action under the bureau's own rules.
     */
    public function pdf(Request $request, string $report, CustomerCreditReports $reports): Response
    {
        $customer = $this->customer($request);
        $check = $reports->find($customer, $report) ?? abort(404);
        $bureauRequest = CreditReportView::for($check)->request;

        abort_unless($bureauRequest?->hasStoredPdf() === true, 404);

        try {
            $encrypted = Storage::disk('local')->get((string) $bureauRequest->pdf_path);
            $bytes = $encrypted === null ? null : Crypt::decryptString($encrypted);
        } catch (Throwable $exception) {
            Log::error('Customer report PDF could not be read', ['request' => $bureauRequest->public_id, 'exception' => $exception::class]);
            $bytes = null;
        }

        abort_if($bytes === null, 404);

        $this->logCustomerAccess($bureauRequest, 'pdf_downloaded_by_customer', $check, $customer);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$check->bureau->value.'-credit-report-'.$check->completed_at?->format('Y-m-d').'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Writes the access to the bureau request's own audit trail, like the
     * request's recordEvent() but with no staff user: the customer is named in
     * the details, never put in audit_logs.user_id (a users id).
     */
    private function logCustomerAccess(CibilCreditRequest|EquifaxCreditRequest|ExperianCreditRequest $bureauRequest, string $action, CreditScoreCheck $check, Customer $customer): void
    {
        $bureauRequest->auditLogs()->create([
            'user_id' => null,
            'action' => $action,
            'changes' => [
                'check' => ['old' => null, 'new' => $check->public_id],
                'customer' => ['old' => null, 'new' => $customer->public_id],
            ],
        ]);
    }
}
