---
paths:
  - 'app/Modules/Customers/**,resources/views/components/⚡credit-score-check.blade.php,app/Modules/CreditScore/Actions/SubmitCreditScoreCheck.php'
---

# Modules Credit Score Actions

## Unlinked credit checks are claimed by verified mobile + PAN
Checks saved before credit_score_checks.customer_id existed (2026-10-16), or while ResolveCustomerAccount failed, have no customer. A later check answered from that stored score is a reused_from repeat view, which CustomerCreditReports::history() hides. So on 2026-10-17 the customer saw a score of 842 on the result page but nothing in My Profile, while the regeneration limit (which matches by PAN) still showed "next refresh 16 Jan 2027". ClaimCreditScoreChecks links checks with customer_id NULL, the same mobile_number AND the same pan_number to the account. submitDetails() runs it right after accountFor() and before the limit decision. Migration 2026_10_17_081924 back-filled existing accounts using the verified_mobile and profile PAN. Never claim on mobile alone or PAN alone, and never move a check that is already on an account. Pinned by CreditCheckAccountTest.
