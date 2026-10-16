---
paths:
  - 'app/Modules/Customers/Services/**,app/Http/Controllers/Portal/**,resources/views/components/⚡credit-score-check.blade.php,app/Modules/CreditScore/Actions/SubmitCreditScoreCheck.php'
---

# Actions

## Portal never calls a bureau; refresh = the public check with context="portal"
Signing in, the dashboard and report pages only read saved credit_score_checks (genuine = provider 'surepass', never demo) and the bureau request the provider stored in raw_response.{cibil,experian,equifax}_request (followed through reused_from). A refresh is the same credit-score-check component (OTP to the registered mobile, fresh consent tick, SubmitCreditScoreCheck reuse/lock/uncertain-hold). The monthly refresh policy is the existing per-bureau public reuse period (SubmitCreditScoreCheck::reuseDays(), 30 days default) — do not add a separate period. Report PDFs are served only from an already-stored encrypted copy; never fetch a provider link for a customer. On the public page the mobile is never prefilled; the signed-in customer's details are prefilled only after an OTP proves the number is theirs (shared browsers).
