---
paths:
  - 'app/Modules/Customers/Services/CustomerCreditReports.php,app/Modules/CreditScore/Actions/SubmitCreditScoreCheck.php,app/Modules/CreditScore/Services/CreditReportRegenerationLimit.php,resources/views/components/⚡credit-score-check.blade.php'
---

# Credit Score Actions

## Checks are free; one new report every 3 months, max 4 a year, per customer and PAN, across bureaus
User decision (2026-10-17), superseding the 30-day refresh policy of 2026-10-16 and the paid-refresh/wallet feature (removed: the Surepass agreement §2.1/§2.1.2/§8.2(c) forbids resale, so never charge customers for bureau checks). CreditReportRegenerationLimit (config customer_portal.regeneration.every_months=3, max_per_year=4) counts NEW reports — genuine, Completed, raw_response.reused_from null — for the customer OR the PAN in the last 12 months. Inside the limit: same bureau + same PAN + same mobile → SubmitCreditScoreCheck::handle(..., reuseSince) shows the saved score; anything else is refused before the bureau call and before the OTP is spent; a PAN-only match on another account gets a generic message (no dates). The bureau's own reuse days still apply on top. The agreement itself sets no frequency — do not cite it as the source of the limit.
