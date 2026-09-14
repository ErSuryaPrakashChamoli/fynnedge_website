<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryType;
use App\Models\LoanProduct;
use App\Modules\Enquiries\Actions\RecordEnquiry;
use App\Modules\Enquiries\Concerns\AnswersEnquirySubmissions;
use App\Modules\Enquiries\Concerns\ValidatesLoanEnquiries;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The enquiry form that sits on every loan product page.
 *
 * The product is NOT a form field. It is the route's own segment, resolved
 * through route-model binding against the loan_products table, so what gets
 * stored is a real foreign key to a real published product — there is no
 * hidden `loan_type` input to edit, and a tampered slug 404s instead of
 * writing a made-up product name into the reporting data.
 */
class LoanEnquiryController extends Controller
{
    use AnswersEnquirySubmissions, ValidatesLoanEnquiries;

    public function store(Request $request, LoanProduct $loanProduct, RecordEnquiry $record): JsonResponse|RedirectResponse
    {
        // Route-bound models skip the published() scope entirely, so a draft or
        // expired product would otherwise still accept enquiries from a stale tab.
        abort_unless($loanProduct->isCurrentlyPublished(), 404);

        $validated = $this->validateLoanEnquiry($request, $loanProduct);

        $outcome = $record->handle(new EnquiryDraft(
            phone: $validated['phone'],
            type: EnquiryType::LoanEnquiry,
            // Derived here, never submitted: this is the column marketing counts on.
            enquirySource: "{$loanProduct->name} Page",
            loanProductId: $loanProduct->id,
            name: $validated['name'],
            email: $validated['email'] ?? null,
            loanAmount: (float) $validated['loan_amount'],
            landingPage: $this->resolveLandingPage($request),
        ));

        return $this->respondTo($request, $outcome);
    }
}
