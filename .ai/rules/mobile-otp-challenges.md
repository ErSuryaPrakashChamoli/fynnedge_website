---
paths:
  - 'app/Filament/Resources/MobileOtpChallenges/**'
---

# Mobile Otp Challenges

## OTP admin shows the full mobile number
User decision (2026-11-20): Mobile OTP Verifications shows the full number, never masked: the list column (copyable, searchable) and the View page (MobileOtpChallengeInfolist, "+91 XXXXXXXXXX", copyable), so staff can find and call back the customer. The OTP code and otp_hash are never shown. Pinned by MobileOtpChallengeAdminTest.
