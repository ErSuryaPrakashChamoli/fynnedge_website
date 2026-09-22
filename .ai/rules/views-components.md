---
paths:
  - 'database/seeders/FlexiHybridTermLoanSeeder.php,app/Support/Calculators/FlexiHybrid*.php,resources/views/components/⚡flexi-hybrid-calculator.blade.php'
---

# Views Components

## Flexi Hybrid lenders: researched terms + explicit structures; Bajaj is default
SUPERSEDES "Flexi Hybrid Term Loan's 4 lender rates are illustrative placeholders". The offered lenders are Bajaj Finance, Tata Capital, Aditya Birla Finance and Piramal Finance (Poonawalla/Kotak deactivated). Their terms come from each lender's own pages (Sept 2026) and live in App\Support\Calculators\FlexiHybridLenderTerms, shared by FlexiHybridTermLoanSeeder and the 2026_09_22 sync migration. Lenders do NOT keep the subsequent tenure fixed (Tata: 1+4, 1+5, 2+5, 2+6; ABFL: 1- or 2-yr holiday on 6/7/8 yrs), so lender_products.hybrid_structures (list of {initial_months, subsequent_months}) wins over the min/max/initial derivation. Two structures can share a total tenure, so the calculator tracks initialTenureMonths too. The calculator opens on DEFAULT_LENDER_SLUG (bajaj-finance), and the "Generic estimate" uses ONLY that lender's structures — never merge structures across lenders. The comparison omits a lender with no rate/structure instead of printing "Available on request". Bajaj's per-tenure split isn't a published table (only "interest-only up to 36 months", 12+48 example); re-verify it with the lender.
