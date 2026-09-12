---
paths:
  - 'app/Models/ContactEnquiry.php,app/Modules/Enquiries/**,app/Http/Controllers/QuickEnquiryController.php,app/Filament/Resources/ContactEnquiries/**'
---

# Contact Enquiries

## contact_enquiries is the single lead table for every website enquiry
There is no `leads` table and no LMS lead model in this app — `contact_enquiries` is it. Both public forms write there and `enquiry_type` (EnquiryType::Contact | QuickEnquiry) is what separates them; name/email/message are nullable because a Quick Enquiry carries only a phone number. Do not add a second enquiries/leads table for a new form — add an EnquiryType case.

Invariants:
- SubmitQuickEnquiry never inserts a second row for a number already present. Open enquiry → bump enquiry_count, return Duplicate. Closed → reopen (status New, handled_at null) + bump. `enquiry_type` is NEVER rewritten on an existing row: a row that arrived via the contact form keeps its name/email/message, which is worth more to whoever works it than a relabel.
- `status` is the lifecycle; `handled_at` predates it and still drives the admin "Handled" icon/filter, so ContactEnquiry::booted() stamps handled_at when status becomes Closed (one direction only — an existing follow-up time is never erased).
- Phone is stored as 10 bare digits. ContactEnquiry::normalizePhone() strips separators and a +91/91/0 prefix and must run BEFORE validation, or the `regex:/^[6-9]\d{9}$/` rule judges a different string than the one stored.
- The public endpoint returns only outcome/title/message. Reopened is reported to the visitor as `created` — whether a number is already in the table is not something a stranger typing numbers gets to learn.
- Two rate limits, both needed: `throttle:quick-enquiry` per IP (AppServiceProvider) and MAX_SUBMISSIONS_PER_HOUR per number inside the action. One IP walks many numbers; many IPs hammer one number. Over-limit is answered as a duplicate, never as an error.
- `phone_verified_at` + SubmitQuickEnquiry's `$phoneVerified` argument are the unused seam for OTP. Wire App\Modules\CreditScore\Actions\RequestMobileOtp/VerifyMobileOtp in front of the action and pass true; nothing else changes.
