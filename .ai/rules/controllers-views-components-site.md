---
paths:
  - 'app/Http/Controllers/QuickEnquiryController.php,resources/views/components/site/quick-enquiry.blade.php'
---

# Controllers Views Components Site

## Quick Enquiry is OTP-verified: two endpoints, one flow
The homepage Quick Enquiry box no longer records a lead from a bare number. POST /quick-enquiry/otp issues a MobileOtpChallenge and writes NOTHING to contact_enquiries; POST /quick-enquiry verifies the code and only then calls RecordEnquiry with phoneVerified: true. This is the OTP seam the enquiries rule described, now wired — phone_verified_at on a quick-enquiry lead is real, not aspirational.

Invariants:
- The challenge is looked up by public_id AND mobile_number, so a challenge verified for one number can never wave through a different one. VerifyMobileOtp also refuses an already-verified challenge, so one code cannot be replayed for a second lead.
- RequestMobileOtp raises its per-number rate limit against the field name `mobileNumber` (the credit-score component's field). This form's field is `phone`, so QuickEnquiryController::issueChallenge() re-raises the message under `phone` or the visitor never sees it.
- Both steps normalise the phone before validating, and both are on throttle:enquiry-forms.
- No SMS gateway is connected: the code is returned as `demo_otp_code` / flashed as `quickEnquiryDemoOtp` and shown on screen under a "Demo mode" alert, same as the journey OTP step and credit-score check. Remove that alert and the JSON field together when a gateway lands.
- The no-JavaScript path still works and is not a second page: both steps are real <form> elements in one component, and which one shows is decided by an inline `display:none` rendered server-side — x-show toggles exactly that inline style, so Alpine takes the switch over on boot. The step is resolved from session('quickEnquiryChallenge') (code just sent) or old('otp_challenge_id') (wrong code bounced back). Do not replace those inline styles with a `hidden` class or the JS-off path breaks silently.
- The loan-page enquiry form (LoanEnquiryController) is NOT OTP-verified. Only the Quick Enquiry box is.
