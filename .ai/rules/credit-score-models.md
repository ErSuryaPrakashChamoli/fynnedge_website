---
paths:
  - 'app/Modules/CreditScore/Actions/SubmitCreditScoreCheck.php,app/Modules/CreditScore/Services/CreditReportRegenerationLimit.php,app/Modules/CreditScore/Models/CreditScoreCheck.php'
---

# Credit Score Models

## Stored public scores never cross bureaus: filter by the answering bureau, not just the page
Both CIBIL-page sources use provider name 'surepass', so bureau+provider+PAN+mobile alone would reuse a CIBIL score as Experian's after the switch flips. Reuse, the uncertain-check hold (previousChecks → CreditScoreCheck::scopeAnsweredBy(page, source)) and the regeneration limit's SAVED_SCORE match (report->sourceBureau() === SubmitCreditScoreCheck::sourceBureau($bureau)) must all compare the answering bureau. raw_response.source_bureau is set for Experian answers, and on the pending row before the call; absent means the page's own bureau. A saved-score match by page alone made live() run a NEW paid pull past the 3-month limit; pinned by CibilPageBureauSwitchTest "E: shows the saved CIBIL score again…".
