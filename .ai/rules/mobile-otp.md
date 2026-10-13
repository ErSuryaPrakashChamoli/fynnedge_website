---
paths:
  - 'app/Modules/MobileOtp/**'
---

# Mobile Otp

## One OTP system: RequestMobileOtp / VerifyMobileOtp / consumeFor, purpose-scoped
Every OTP (API, Quick Enquiry, journey, credit score) goes through App\Modules\MobileOtp — never add a second OTP table/action. Issue: RequestMobileOtp::handle(number, MobileOtpPurpose, ip) (limits+cooldown under a per-number cache lock, short DB transaction, SMS sent OUTSIDE it). Verify: MobileOtpChallenge::findForVerification(id, number, purpose) then VerifyMobileOtp (row-locked). Protected write: $challenge->consumeFor(number, purpose) inside the same DB transaction as the record it unlocks. `status` (challenge) and `sms_status` (gateway) are separate: an accepted SMS is never "verified"; "expired" is derived (effectiveStatus/whereEffectiveStatus), never stored. The plaintext code must never be returned, flashed, rendered or logged; LogSmsProvider only runs when APP_ENV is local/testing. Tests: fakeAreachSms()/lastTextedOtp() in tests/Pest.php.
