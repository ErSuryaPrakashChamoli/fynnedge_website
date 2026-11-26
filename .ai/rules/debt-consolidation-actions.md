---
paths:
  - app/Modules/DebtConsolidation/Actions/UploadDebtDocument.php
  - 'app/Modules/DebtConsolidation/Actions/**'
---

# Debt Consolidation Actions

## Sniff upload types from the bytes; UploadedFile::getMimeType() trusted a .pdf name
A PHP file named statement.pdf passed getMimeType() as application/pdf. UploadDebtDocument checks (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) against PDF/JPEG/PNG. Tests must upload real magic bytes (createWithContent('x.pdf', '%PDF-1.4 …')); UploadedFile::fake()->create(..., 'application/pdf') has no PDF header and is (correctly) refused.

## Debt Consolidation intake, consent and closure rules settled in acceptance (2026-11-25)
- **Intake:** new customer requests go through `DebtConsolidationSettings::ensureAccepting()`. This covers SubmitDebtEnquiry, OpenDebtApplication and SubmitDebtApplication, and the components check it before using an OTP. Switching the service off refuses intake even from an open form; existing cases stay served (portal, API, staff).
- **Submission:** submit re-checks that income and profile are complete (steps can be skipped via Livewire calls).
- **Resubmission:** a resubmission from InformationPending moves the case to AssessmentInProgress and alerts staff.
- **Lender-sharing consent:** required only to open or Submit a lender application. The lender's later decisions are always recordable, and the team is alerted on withdrawal.
- **Closure payment:** a closure payment is confirmed only against a known amount: final_closure_amount, or (full closure only) the loan's quotation, even one recorded after the closure opened. A part-payment never uses the full quotation.
- **NOC:** must be of type noc_closure_letter, for that liability (or untied), and used by no other closure.
- **Auto-complete:** only from ClosureInProgress.
- **Maker-checker:** the maker is the author OR anyone in the audit trail who changed the draft (MakerChecker::isMaker).
