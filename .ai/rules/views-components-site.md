---
paths:
  - 'app/Support/Calculators/**,app/Http/Controllers/LoanProductController.php,resources/views/loans/**,resources/views/components/site/flexi-hybrid-hero.blade.php'
---

# Views Components Site

## Hybrid-repayment loan types get their own view + calculator, gated by LoanCategory::isHybridRepayment()
Flexi Hybrid Term Loan (LoanCategory::FlexiHybridTermLoan) repays in two stages — interest-only initial tenure, then principal+interest for the rest — which the standard single-tenure EMI stack (⚡emi-calculator.blade.php, EmiCalculator::calculate()) has no concept of. Rather than branching two-stage logic into that shared component (used by 11 other categories, documented as needing zero changes for an ordinary EMI product), this got its own parallel-but-reusing stack: EmiCalculator::calculateHybrid() (reuses calculate() internally for the subsequent stage), a dedicated ⚡flexi-hybrid-calculator.blade.php Livewire component, and LoanProductController::show() picks loans.show-flexi-hybrid instead of loans.show when $loanProduct->category->isHybridRepayment(). Tenure split: loan_products.default_initial_tenure_months (generic) + lender_products.initial_tenure_months (per-lender override, via LenderProduct::effectiveInitialTenureMonths()/hybridTenureOptions()) — per-lender structures are derived by FlexiHybridTenure (see below). Any future hybrid-structured product should reuse this same pattern (add its own LoanCategory case + isHybridRepayment() branch), not duplicate it again.

## Flexi Hybrid tenure structure is per lender, derived by FlexiHybridTenure
Each lender offers its own "initial + subsequent" structure (Kotak 1 + 5, Tata Capital 2 + 5, Piramal 2 + 5, Bajaj 2 + 6 or 3 + 6 years). No new columns: FlexiHybridTenure::options(min_tenure_months, max_tenure_months, initial_tenure_months) derives them — subsequent = min − initial stays fixed, each extra 12 months up to max lengthens the initial tenure (Bajaj = 96/108/24, Kotak = 72/72/12). Use LenderProduct::hybridTenureOptions(); ⚡flexi-hybrid-calculator and flexi-hybrid-hero read it. The lender-comparison-table chart deliberately does NOT show initial/subsequent tenure columns (removed at the user's request, 2026-09-22). Never hardcode lender names or splits in code — change the lender's tenure fields instead.
