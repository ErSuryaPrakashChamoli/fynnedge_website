---
paths:
  - 'app/Support/Rates/**,app/Models/LenderProductRate.php,app/Models/LenderProduct.php,app/Models/LoanProduct.php,resources/views/components/site/lender-comparison-table.blade.php,resources/views/components/site/loan-enquiry*.blade.php,app/Support/Calculators/LoanCalculatorPreset.php'
---

# Support Calculators

## Every public interest rate comes from RateBook — never from a column
Public rates (comparison table, loan hero "Interest from", enquiry form "starting at", journey results, calculators' default rate/range, [rate:] tokens) are read ONLY through App\Support\Rates\RateBook: published + verified lender_product_rates rows, filtered at READ time by effective_from/until (IST calendar days) and promo window, so expiry needs no cache TTL or cron. lender_products.interest_rate_from/to are now a read-only mirror written by RateWorkflow::syncOfferRate() for the eligibility FOIR estimate — never display them. loan_products.min/max/default_interest_rate are calculator slider config only. The product "from X%" headline = lowest live rate with blank borrower_segment (segment-only rates never drive it). Card products are per month (LoanCategory::rateUnit()); never show a computed ×12 annual figure (issuers state compounded APRs that differ). Tests publish rates with publishRate() in tests/Pest.php. Supersedes the "generated from the loan product's own min_interest_rate" line in contact-enquiries.md.
