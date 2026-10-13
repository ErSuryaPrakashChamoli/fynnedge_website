---
paths:
  - 'app/Http/Controllers/QuickEnquiryController.php,resources/views/components/site/quick-enquiry.blade.php'
---

# Controllers Views Components Site

## Quick Enquiry is OTP-verified: two endpoints, one flow
The homepage Quick Enquiry box no longer records a lead from a bare number. POST /quick-enquiry/otp issues a MobileOtpChallenge and writes NOTHING to contact_enquiries; POST /quick-enquiry verifies the code and only then calls RecordEnquiry with phoneVerified: true. This is the OTP seam the enquiries rule described, now wired — phone_verified_at on a quick-enquiry lead is real, not aspirational.

Invariants:
- The challenge is looked up by public_id AND mobile_number AND purpose (loan_enquiry) via MobileOtpChallenge::findForVerification(), so a challenge for one number or form can never wave through another. The lead is written only after consumeFor() redeems the verified challenge, in the same transaction — one verification, one lead.
- store() takes either the code (the box's own step two) or no code for a challenge already verified via POST /api/v1/mobile-otp/verify.
- App\Modules\MobileOtp\Actions\RequestMobileOtp throws MobileOtpThrottled (cooldown/caps); QuickEnquiryController::issueChallenge() turns it, and a failed SMS, into an error on `phone` so the visitor sees it.
- Both steps normalise the phone before validating, and both are on throttle:enquiry-forms.
- The code is texted via Areach (docs/mobile-otp-integration.md) and never returned, flashed or rendered. The old `demo_otp_code` / `quickEnquiryDemoOtp` / "Demo mode" alert are gone from all three OTP flows — do not reintroduce a code in any response.
- The no-JavaScript path still works and is not a second page: both steps are real <form> elements in one component, and which one shows is decided by an inline `display:none` rendered server-side — x-show toggles exactly that inline style, so Alpine takes the switch over on boot. The step is resolved from session('quickEnquiryChallenge') (code just sent) or old('otp_challenge_id') (wrong code bounced back). Do not replace those inline styles with a `hidden` class or the JS-off path breaks silently.
- The loan-page enquiry form (LoanEnquiryController) is NOT OTP-verified. Only the Quick Enquiry box is.
