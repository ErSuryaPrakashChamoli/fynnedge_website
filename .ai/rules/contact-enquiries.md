---
paths:
  - 'app/Models/ContactEnquiry.php,app/Modules/Enquiries/**,app/Http/Controllers/QuickEnquiryController.php,app/Http/Controllers/LoanEnquiryController.php,app/Support/Enquiries/**,app/Filament/Resources/ContactEnquiries/**,resources/views/components/site/loan-enquiry*.blade.php'
---

# Contact Enquiries

## contact_enquiries is the single lead table for every website enquiry
There is no `leads` table and no LMS lead model in this app — `contact_enquiries` is it. All three public forms write there (contact page, homepage Quick Enquiry, loan-page enquiry) and `enquiry_type` (EnquiryType::Contact | QuickEnquiry | LoanEnquiry) separates them; name/email/message are nullable because a Quick Enquiry carries only a phone number. Do not add a second enquiries/leads table for a new form — add an EnquiryType case and call RecordEnquiry.

App\Modules\Enquiries\Actions\RecordEnquiry is the ONLY write path from a public form. Controllers validate and build an EnquiryDraft; the action alone decides create / duplicate / reopen, so the answer cannot drift between forms.

The four source columns each mean one thing, and nothing else:
- `loan_product_id` — a real FK, the only thing reporting groups by. Never a product name string.
- `source` — the channel ("website"). This is Lead Source.
- `enquiry_source` — the placement label ("Personal Loan Page", "Homepage Quick Enquiry"), stored not derived, so it stays readable after a rename.
- `source_url` — the landing page path. There is no separate landing_page column; do not add one.

Invariants:
- Nothing about the source is ever accepted from the request body. The loan product comes from the route segment (`/loans/{loanProduct:slug}/enquiry`, route-model bound, `isCurrentlyPublished()` enforced — route binding skips the published scope); enquiry_source is derived server-side, and QuickEnquiryController maps its `source` input through a fixed PLACEMENTS list. A form field for any of these would let a visitor write the marketing numbers.
- Duplicates are judged per (phone, loan_product_id). Same number + same product + open → bump enquiry_count, return Duplicate. Same number + DIFFERENT product → a new row, because that is a second interest and collapsing it loses one. Settled (Closed/Converted/Rejected) + same product → reopen. A draft with no product matches any row for that number.
- `enquiry_type` and `enquiry_source` are NEVER rewritten on an existing row; details are filled in but never overwritten (a repeat that finally gives us a name is kept, a blank one must not erase the name we had).
- `status` is the lifecycle; `handled_at` predates it and still drives the admin "Handled" icon/filter, so ContactEnquiry::booted() stamps handled_at when status becomes settled (one direction only — an existing follow-up time is never erased).
- Phone is stored as 10 bare digits. ContactEnquiry::normalizePhone() strips separators and a +91/91/0 prefix and must run BEFORE validation, or the `regex:/^[6-9]\d{9}$/` rule judges a different string than the one stored.
- Public endpoints return only outcome/title/message. Reopened is reported as `created` — whether a number is on file is not something a stranger typing numbers gets to learn.
- Two rate limits, both needed: `throttle:enquiry-forms` per IP (AppServiceProvider) and MAX_SUBMISSIONS_PER_HOUR per (number, product) inside RecordEnquiry. One IP walks many numbers; many IPs hammer one number. Scoping the inner cap to the product is what lets a real customer enquire about a second loan immediately. Over-limit is answered as a duplicate, never as an error.
- Amount limits come from the product row via App\Support\Enquiries\LoanEnquiryAmount, used by both the validation rule and the field's helper text. One definition — never hardcode a range in Blade.
- `phone_verified_at` + EnquiryDraft's `$phoneVerified` are the unused seam for OTP. Wire App\Modules\CreditScore\Actions\RequestMobileOtp/VerifyMobileOtp in front of RecordEnquiry and pass true; nothing else changes.

## Enquiry form copy is admin-editable; the loan page hero IS the enquiry section
App\Support\Enquiries\EnquiryFormContent resolves the heading/description/form label/button from a published MarketingSection (`loan_enquiry_form`, `home_quick_enquiry`) and falls back to the hardcoded wording — the same additive contract as every other placement, so an empty table renders the site exactly as before. `:product` in an admin string is substituted with the product name, or a landing page's own title. Add new placements to MarketingSectionForm's Select or they cannot be created.

Deliberately NOT editable: the form's "Get up to ₹50 Lakh starting at 10.49%" headline and the amount range, both generated from the loan product's own min_interest_rate/max_amount (via LoanEnquiryAmount). Those are financial claims and must not drift from the calculator and lender table.

Layout contract for loan pages (loans/show, loans/landing-page, loans/show-flexi-hybrid):
- The enquiry form lives in the hero's right column, not in a band further down — it must be on screen without scrolling. `x-site.loan-enquiry` renders the page's h1; the views must not render a second one.
- Every container on these pages is `mx-auto max-w-7xl px-6 lg:px-8`, matching x-site.header and x-site.footer. They were max-w-5xl, which is what left the content visibly inset from the nav. Prose blocks cap at max-w-3xl inside that width.
- The article below the hero uses `pb-14` with no top padding, because every block in it already carries `mt-12`.
- show-flexi-hybrid keeps its own bespoke hero; the form replaced the two-stage repayment card in its right column, and that breakdown still renders below via `x-site.repayment-stages` — do not re-add it to the hero.
