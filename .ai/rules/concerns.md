---
paths:
  - 'app/Filament/RelationManagers/**,app/Filament/Concerns/**'
---

# Concerns

## Content versioning reuses audit logs — RestorableAuditLogsRelationManager
Do NOT build a separate version-snapshot table. `RestorableAuditLogsRelationManager` (extends AuditLogsRelationManager, in app/Filament/RelationManagers/) adds a "Restore this version" action on `updated` audit rows — it sets the record's fields back to that row's `old` values and saves, which itself writes a fresh ordinary audit entry (provenance chain, no special-casing). Only registered on pure-content resources with no business-relevant numeric/eligibility fields: Article, Faq, Testimonial, Banner, CompanyPhoto, Page, LoanLandingPage, MarketingSection, NavigationLink. Deliberately NOT on LoanProduct/LenderProduct/Lender/Application/EligibilityRuleSet/Rule/Condition — those keep the plain read-only `AuditLogsRelationManager` from Phase 1, since a blind field restore there bypasses form validation and could produce an inconsistent calculator/eligibility config. `App\Filament\Concerns\FormatsAuditChanges` holds the shared "Label: old → new" rendering used by both the History tab and the AdminActivity page — extend that trait, don't duplicate the formatting.
