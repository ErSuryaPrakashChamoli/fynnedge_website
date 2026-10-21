---
paths:
  - 'app/Modules/CreditScore/Services/CreditScoreChecksCsv.php,app/Filament/Resources/CreditScoreChecks/Pages/ListCreditScoreChecks.php'
---

# Credit Score Checks Pages

## Credit Score Checks CSV: location comes from the profile, then the bureau report, gated by viewReport
User decision (2026-10-21): "Download CSV" on Credit Score Checks exports the filtered/sorted table rows (Name, Mobile, Bureau, Score, Location, PAN, Date IST). credit_score_checks stores no location, so Location = the customer's city/state/PIN when filled, else the first address in the bureau report behind the check (CreditScoreCheck::bureauRequest() → hasReport()). The address is part of the full report, so it is filled ONLY when the downloader can('viewReport', $request); never drop that check. CRIF public, demo and failed checks stay blank. Cells go through safe() (formula neutralising). Pinned by tests/Feature/CreditScore/CreditScoreChecksCsvTest.php.
