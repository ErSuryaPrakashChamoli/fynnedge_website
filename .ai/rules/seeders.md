---
paths:
  - 'database/seeders/**'
  - database/seeders/LegalPageSeeder.php
---

# Seeders

## Override a factory's nested-relation FK via make()'s argument, never via `+` after toArray()
`SomeFactory::factory()->make()->toArray() + ['loan_product_id' => $realId]` silently does nothing — `toArray()` already contains that key (resolved from the factory's own nested `SomeOtherModel::factory()` default, which persists a throwaway row as a side effect), and PHP's `+` keeps the left-hand array's value on a key collision. This caused DatabaseSeeder's Personal Loan and (initially) JourneySeeder's Home Loan LenderProduct rows to attach to a random stray LoanProduct instead of the real one, which in turn made EligibilitySeeder silently skip both (`if (! $lenderProduct) { return; }`) — zero EligibilityRuleSets were ever seeded. Always pass the override into `make([...])`/`create([...])` directly, e.g. `LenderProduct::factory()->make(['loan_product_id' => $realId, ...])->toArray()`, so the factory never resolves the nested default at all. After touching seeders that wire lender/loan-product relationships, verify with a query — don't trust that it ran without erroring.

## Disclaimer now follows the numbered-accordion pattern (like Privacy Policy &amp; Terms)
disclaimer() was rewritten from a 5-section plain <h2>/<p> page to a 12-section native <details>/<summary> accordion (same not-prose wrapper, numbered-badge, and chevron markup as privacyPolicy()/terms()). Section 12 ("Contact FynnEdge") deliberately links to /contact and /grievance instead of hardcoding phone/email/address, so it can't go stale relative to the Settings-driven Contact page. grievance() and creditReportTerms() are the two remaining legal pages still in the old plain-HTML/placeholder format if they're ever revisited.
