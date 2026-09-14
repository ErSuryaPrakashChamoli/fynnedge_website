---
paths:
  - 'app/Http/Controllers/QuickEnquiryPageController.php,app/Modules/Enquiries/Concerns/ValidatesLoanEnquiries.php,resources/views/components/site/loan-enquiry-form.blade.php,resources/views/quick-enquiry.blade.php'
---

# Site Views

## Quick Enquiry page: the one form where the visitor picks the product
GET /quick-enquiry (quick-enquiry.show, linked from the homepage hero) renders x-site.loan-enquiry-form in selector mode (:loan-products + :selected, ?loan=slug preselects). It posts to POST /quick-enquiry/apply (throttle:enquiry-forms), NOT the phone-only OTP /quick-enquiry endpoint.
The dropdown is the visitor's stated interest, so loan_product IS accepted from the body — but only as a slug matching LoanProduct::published() (Rule::in), stored as the real FK. enquiry_type = LoanEnquiry, enquiry_source = 'Quick Enquiry Page' (derived server-side). Not OTP-verified, like the loan-page form.
Both loan-page and this form validate via App\Modules\Enquiries\Concerns\ValidatesLoanEnquiries — change rules there, never inline in one controller.
The Alpine loanEnquiryForm gets per-product {amount, rate, min, max, rangeHint, rangeMessage} in config.products for both modes; headline/range hint/client range check read `selected`, tracking reads `this.tracking`. The select adds a 5th affix box on that page (loan pages still have 4).
