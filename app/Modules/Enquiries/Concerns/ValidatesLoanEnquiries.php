<?php

namespace App\Modules\Enquiries\Concerns;

use App\Models\ContactEnquiry;
use App\Models\LoanProduct;
use App\Support\Enquiries\LoanEnquiryAmount;
use Illuminate\Http\Request;

/**
 * The rules every loan enquiry form is judged by — the loan-page form, where the
 * product is the URL segment, and the Quick Enquiry page, where the visitor picks
 * it from a dropdown. One definition, so the two can never disagree about what a
 * valid mobile number or loan amount is.
 */
trait ValidatesLoanEnquiries
{
    /**
     * Normalises then validates, in that order: the visitor may paste
     * "+91 98765-43210" or type "5,00,000", and the rules must judge the string
     * that will actually be stored.
     *
     * @param  array<string, array<int, mixed>>  $extraRules
     * @param  array<string, string>  $extraMessages
     * @return array<string, mixed>
     */
    protected function validateLoanEnquiry(Request $request, ?LoanProduct $loanProduct, array $extraRules = [], array $extraMessages = []): array
    {
        $request->merge([
            'phone' => ContactEnquiry::normalizePhone($request->input('phone')),
            // The field displays Indian digit grouping (5,00,000) as it is typed.
            'loan_amount' => preg_replace('/\D+/', '', (string) $request->input('loan_amount')),
        ]);

        /*
         * Without a product there is no range to judge the amount against. That
         * only happens when the product itself failed its own rule, so the
         * visitor is already being told to pick one.
         */
        $range = $loanProduct ? LoanEnquiryAmount::rangeFor($loanProduct) : null;

        return $request->validate([
            ...$extraRules,
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
            'loan_amount' => $range
                ? ['required', 'numeric', "min:{$range['min']}", "max:{$range['max']}"]
                : ['required', 'numeric'],
            // Honeypot, as on the newsletter form: a person never fills a hidden field.
            'website' => ['prohibited'],
        ], [
            'name.required' => 'Please enter your name.',
            'phone.required' => 'Please enter a valid 10-digit mobile number.',
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
            'email.email' => 'Please enter a valid email address.',
            'loan_amount.required' => 'Please enter the loan amount you need.',
            ...($range ? [
                'loan_amount.min' => LoanEnquiryAmount::rangeMessage($range),
                'loan_amount.max' => LoanEnquiryAmount::rangeMessage($range),
            ] : []),
            ...$extraMessages,
        ], ['loan_amount' => 'loan amount']);
    }
}
