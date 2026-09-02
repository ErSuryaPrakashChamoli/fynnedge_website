---
paths:
  - 'app/Models/**,app/Modules/**/Models/*.php'
---

# Modules Models

## How to make an existing model auditable
Do NOT build a second audit system. To add change-history to a model: (1) `use Auditable` (app/Models/Concerns/Auditable.php) — it writes an immutable row to `audit_logs` on create/update/delete via native Eloquent events. (2) If the model has any resource with its own Edit page, add `AuditLogsRelationManager::class` (app/Filament/RelationManagers/AuditLogsRelationManager.php) to that Resource's `getRelations()` — it's a single shared read-only "History" tab reused by every audited resource, gated on the `View:AuditLog` permission (granted to `super_admin` only by default). (3) If the model has any column holding a password, API key/secret, token, or customer PII/KYC/credit detail, override `auditExcept()` to strip those keys before a row is ever written. Currently audited: Application, Lender, LoanProduct, LenderProduct, EligibilityRuleSet, EligibilityRule, EligibilityRuleCondition. A model nested only inside a repeater/relation manager (no own Edit page, e.g. EligibilityRuleCondition) can still use the trait for data completeness even with no dedicated History UI to view it in yet.
