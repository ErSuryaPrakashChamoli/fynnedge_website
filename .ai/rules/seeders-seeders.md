---
paths:
  - 'database/seeders/FlexiHybridTermLoanSeeder.php,database/seeders/JourneySeeder.php'
---

# Seeders Seeders

## Flexi Hybrid Term Loan's 4 lender rates are illustrative placeholders, not real terms
FlexiHybridTermLoanSeeder seeds real-looking but illustrative commercial terms (rate, fees, tenure range, initial_tenure_months) for Bajaj Finance/Tata Capital/Piramal Finance/Kotak Mahindra Bank on the flexi-hybrid-term-loan product — chosen only so the comparison table, calculator and "compare all lenders" feature have real, genuinely-differing numbers to compute against (per user request, so those UI features are demoable end-to-end). None of these figures are sourced from an actual rate card. Replace with confirmed terms via Admin → Lender Offers before this page is treated as production-accurate; don't assume these numbers are real when reading the seeder or the live page. The product-level generic calculator bounds on the LoanProduct row itself (set in JourneySeeder) are the same illustrative-placeholder convention every other seeded LoanProduct uses.
