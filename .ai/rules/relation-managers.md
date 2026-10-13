---
paths:
  - 'app/Filament/Resources/LenderProductRates/**,app/Support/Rates/RateWorkflow.php,app/Filament/RelationManagers/**'
---

# Relation Managers

## Rate versions are rows with a draft→approval→publish workflow, not audit-log restores
Deliberate exception to concerns.md's "reuse audit logs for versioning": a lender rate is a financial claim, so each version is its own lender_product_rates row sharing series_id. Only Draft is editable (model throws on editing/deleting anything submitted/published/archived; EditLenderProductRate redirects to View). All transitions go through RateWorkflow (submit/publish/reject/withdraw/draftFrom), which re-checks Submit:/Publish:/Create:LenderProductRate AND state, and RatePublicationRules (verified, https source, checked within 90 days, unit matches category, promo has end date…). Rollback = draftFrom(old version) → re-approved; never a blind field restore. super_admin bypasses policies via Gate::before, so state rules must live in RateWorkflow/model, not the policy. Roles come from RateRoles::grant() (Marketing = editor, Marketing Publisher, Rate Reviewer), called by RoleSeeder last and by a migration — deploys don't run seeders.
