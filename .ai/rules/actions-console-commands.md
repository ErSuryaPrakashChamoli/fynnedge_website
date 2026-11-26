---
paths:
  - 'app/Modules/DebtConsolidation/Actions/AnonymiseDebtApplication.php,app/Console/Commands/DebtConsolidationEnforceRetentionCommand.php'
---

# Actions Console Commands

## Erasure anonymises a case; consents and dates stay, lender-involved cases are refused
Owner decision M3 (2026-11-26). AnonymiseDebtApplication blanks name/email/location/declared amounts/UTM and the customer and lead links, sets mobile to DebtApplication::ANONYMISED_MOBILE (column is NOT NULL; isAnonymised() checks it), deletes profile, liabilities+quotations, collateral, documents+reports (and their encrypted files), snapshots, scenarios, matches, unsubmitted lender applications, notes and their audit rows; keeps reference, dates, consents and status history (notes blanked). It bypasses the snapshot/status-history model guards with direct queries on purpose — the only sanctioned exception. Refused once any lender application is past NotSubmitted or a closure exists (OD8 record-keeping). Used by the retention job for abandoned drafts and by the case-page "Anonymise personal data" action (needs Delete:DebtApplication). The linked lead-inbox ContactEnquiry is NOT touched; its id goes into the event.
