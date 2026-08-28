---
paths:
  - 'app/**'
---

# App

## Loan journey & eligibility logic must be config-driven, never per-product branching
Never write `if ($loanType === 'personal')` style branches in controllers/services. Journey steps/fields live in journey_definitions/journey_steps/journey_step_fields tables; lender eligibility criteria live in eligibility_rule_sets/eligibility_rules (versioned, effective-dated). Personal Loan is the first product built end-to-end, but every engine (journey, eligibility, FOIR, obligations) must work for Home Loan, LAP, Business Loan and Credit Cards without code changes — only new rows/config.

## Order by id as a tiebreaker whenever sorting by created_at across fast writes
MySQL timestamp/datetime columns here have second-level precision. Two rows written within the same request (e.g. a "created" then "updated" audit log for the same save) can share an identical created_at, making ->latest('created_at') alone order them arbitrarily. Always add a secondary ->orderByDesc('id') (or orderBy) when the relative order of same-second rows matters. See Auditable::auditLogs().
