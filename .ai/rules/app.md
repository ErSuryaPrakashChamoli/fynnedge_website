---
paths:
  - 'app/**'
---

# App

## Loan journey & eligibility logic must be config-driven, never per-product branching
Never write `if ($loanType === 'personal')` style branches in controllers/services. Journey steps/fields live in journey_definitions/journey_steps/journey_step_fields tables; lender eligibility criteria live in eligibility_rule_sets/eligibility_rules (versioned, effective-dated). Personal Loan is the first product built end-to-end, but every engine (journey, eligibility, FOIR, obligations) must work for Home Loan, LAP, Business Loan and Credit Cards without code changes — only new rows/config.
