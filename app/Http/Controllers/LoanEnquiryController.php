<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryType;
use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use App\Modules\Enquiries\Actions\RecordEnquiry;
use App\Modules\Enquiries\Concerns\AnswersEnquirySubmissions;
use App\Modules\Enquiries\DataTransferObjects\EnquiryDraft;
use App\Support\Enquiries\LoanEnquiryAmount;
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
    use AnswersEnquirySubmissions;

    public function store(Request $request, LoanProduct $loanProduct, RecordEnquiry $record): JsonResponse|RedirectResponse
    {
        // Route-bound models skip the published() scope entirely, so a draft or
        // expired product would otherwise still accept enquiries from a stale tab.
        abort_unless($loanProduct->isCurrentlyPublished(), 404);

        $request->merge([
            'phone' => ContactEnquiry::normalizePhone($request->input('phone')),
            // The field displays Indian digit grouping (5,00,000) as it is typed.
            'loan_amount' => preg_replace('/\D+/', '', (string) $request->input('loan_amount')),
        ]);

        $range = LoanEnquiryAmount::rangeFor($loanProduct);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // Same rule the journey's OTP step uses: Indian mobile numbers start 6-9.
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            /*
             * `email:rfc` deliberately, NOT `email:rfc,dns` — a live MX lookup on
             * a public endpoint is a network call an attacker controls the volume
             * of, and it rejects real addresses behind split-horizon DNS. Same
             * reasoning as the newsletter form.
             */
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'loan_amount' => ['required', 'numeric', "min:{$range['min']}", "max:{$range['max']}"],
            // Honeypot, as on the newsletter form: a person never fills a hidden field.
            'website' => ['prohibited'],
        ], [
            'name.required' => 'Please enter your name.',
            'phone.required' => 'Please enter a valid 10-digit mobile number.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
            'email.email' => 'Please enter a valid email address.',
            'loan_amount.required' => 'Please enter the loan amount you need.',
            'loan_amount.min' => LoanEnquiryAmount::rangeMessage($range),
            'loan_amount.max' => LoanEnquiryAmount::rangeMessage($range),
        ], ['loan_amount' => 'loan amount']);

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
