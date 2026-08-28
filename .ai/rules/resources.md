---
paths:
  - 'app/Filament/Resources/**'
---

# Resources

## A model scoped to a lender product gets its own top-level resource, not a nested relation manager
Filament relation managers cannot themselves declare further relation managers. Any model keyed off `lender_product_id` (EligibilityRuleSet, LenderProductDocumentRequirement, etc.) must be its own top-level Resource with a `lender_product_id` Select using `getOptionLabelFromRecordUsing(fn (LenderProduct $r) => "{$r->lender->name} — {$r->loanProduct->name}")`, not nested under LoanProductResource's LenderProductsRelationManager.

Also: JourneySessionResource already owns navigationLabel/modelLabel "Applications"/"application" for the pre-selection eligibility funnel. The post-selection Application model (lender chosen, documents, submission) uses "Loan Applications"/"loan application" instead to avoid nav-label collision — keep this distinction if either resource is renamed.
