---
paths:
  - 'database/seeders/data/**,app/Support/Rates/RateAuditImport.php,app/Support/Rates/RateText.php'
---

# Rates

## Match rate data to loan products by category, never by slug — production slugs differ
Production's personal loan product is "Personal Loans" at /loans/personal-loans (local: personal-loan). The first audit import keyed on loan_products.slug, silently matched none of its lenders, queued no personal-loan rates, and the page showed none. RateAuditImport now keys on LoanCategory value (the dataset's product_slug values ARE category values); migration 2026_10_14_125543 re-ran it, replacing only untouched pre-audit placeholders (draft, created_by/updated_by null, source_notes starting with PLACEHOLDER_NOTE). [rate:…] tokens resolve by product slug first, then by loan category, so [rate:personal-loan] works on production too. Never assume a local slug exists in production; key code and data on LoanCategory.
