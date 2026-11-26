---
paths:
  - 'app/Modules/DebtConsolidation/Services/MakerChecker.php,app/Modules/DebtConsolidation/Actions/**'
---

# Modules Debt Consolidation Actions

## Quotation, payment and closure verification need an independent second person, no exemption
Owner decision OPS-D1 (2026-11-26): VerifyForeclosureQuotation, RecordClosurePayment::confirm and UpdateClosureStatus → ClosureVerified call MakerChecker::ensureIndependent(). The checker must not have recorded the record (recorded_by/created_by), changed it (audit log), or recorded any of its status changes. No exemption, super admin included — unlike lender-policy/scoring approval, where isMaker() + super-admin exemption still applies. Enforced in the domain actions (not just Filament) so every path is covered. Tests use $this->checker for the second step; never let a test reach the NOC/amount rules through the same user, or it silently tests the independence rule instead.
