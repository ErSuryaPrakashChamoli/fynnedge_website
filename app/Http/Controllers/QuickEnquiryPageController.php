<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryType;
use App\Enums\LenderStatus;
use App\Models\Lender;
use App\Models\LoanProduct;
use App\Modules\Enquiries\Actions\RecordEnquiry;
use App\Modules\Enquiries\Concerns\AnswersEnquirySubmissions;
use App\Modules\Enquiries\Concerns\ValidatesLoanEnquiries;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use App\Support\Enquiries\QuickEnquiryPageContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The standalone Quick Enquiry page: the loan-page enquiry form, with the
 * product picked from a dropdown instead of taken from the page it sits on.
 *
 * That dropdown is the one place a visitor names the product, and deliberately
 * so — it is their stated interest. It is still never stored as written: the
 * slug must match a product that is published right now, and the lead carries
 * that product's real id. `enquiry_source` stays derived server-side, so this
 * page is reported as itself rather than as whichever product page was picked.
 *
 * The page's wording is admin-editable (Website Settings → Quick Enquiry Page)
 * through QuickEnquiryPageContent.
 */
class QuickEnquiryPageController extends Controller
{
    use AnswersEnquirySubmissions, ValidatesLoanEnquiries;

    private const ENQUIRY_SOURCE = 'Quick Enquiry Page';

    public function show(Request $request): View
    {
        $content = QuickEnquiryPageContent::resolve();
        $loanProducts = LoanProduct::query()->published()->orderedForDisplay()->get();

        return view('quick-enquiry', [
            'content' => $content,
            'loanProducts' => $loanProducts,
            // ?loan=home-loan preselects the dropdown, so any page can link here with its own product chosen.
            'selectedProduct' => $loanProducts->firstWhere('slug', $request->query('loan'))?->slug,
            // Lenders with an uploaded logo lead, so the strip opens on real logos rather than initials.
            'lenders' => $content['show_lenders']
                ? Lender::query()->where('status', LenderStatus::Active)->orderBy('name')->get()
                    ->sortByDesc(fn (Lender $lender): bool => filled($lender->logoUrl()))
                    ->values()
                : collect(),
        ]);
    }

    public function store(Request $request, RecordEnquiry $record): JsonResponse|RedirectResponse
    {
        // Resolved through published(), so a draft or expired product picked from
        // a stale tab fails validation instead of collecting leads.
        $loanProducts = LoanProduct::query()->published()->get();
        $loanProduct = $loanProducts->firstWhere('slug', $request->input('loan_product'));

        $validated = $this->validateLoanEnquiry($request, $loanProduct, [
            'loan_product' => ['required', 'string', Rule::in($loanProducts->pluck('slug'))],
        ], [
            'loan_product.required' => 'Please select a loan type.',
            'loan_product.in' => 'Please select a loan type.',
        ]);

        $outcome = $record->handle(new EnquiryDraft(
            phone: $validated['phone'],
            type: EnquiryType::LoanEnquiry,
            enquirySource: self::ENQUIRY_SOURCE,
            loanProductId: $loanProduct->id,
            name: $validated['name'],
            email: $validated['email'] ?? null,
            loanAmount: (float) $validated['loan_amount'],
            landingPage: $this->resolveLandingPage($request),
        ));

        return $this->respondTo($request, $outcome);
    }
}
