---
paths:
  - 'app/Support/Calculators/**,app/Http/Controllers/LoanProductController.php,resources/views/loans/**,resources/views/components/site/flexi-hybrid-hero.blade.php'
---

# Views Components Site

## Hybrid-repayment loan types get their own view + calculator, gated by LoanCategory::isHybridRepayment()
Flexi Hybrid Term Loan (LoanCategory::FlexiHybridTermLoan) repays in two stages — interest-only initial tenure, then principal+interest for the rest — which the standard single-tenure EMI stack (⚡emi-calculator.blade.php, EmiCalculator::calculate()) has no concept of. Rather than branching two-stage logic into that shared component (used by 11 other categories, documented as needing zero changes for an ordinary EMI product), this got its own parallel-but-reusing stack: EmiCalculator::calculateHybrid() (reuses calculate() internally for the subsequent stage), a dedicated ⚡flexi-hybrid-calculator.blade.php Livewire component, and LoanProductController::show() picks loans.show-flexi-hybrid instead of loans.show when $loanProduct->category->isHybridRepayment(). Tenure split: loan_products.default_initial_tenure_months (generic) + lender_products.initial_tenure_months (per-lender override, via LenderProduct::effectiveInitialTenureMonths()/subsequentTenureMonths()) — except Flexi Hybrid itself, whose split is now fixed in FlexiHybridTenure (see below). Any future hybrid-structured product should reuse this same pattern (add its own LoanCategory case + isHybridRepayment() branch), not duplicate it again.

## Flexi Hybrid tenure split is a fixed business rule (FlexiHybridTenure)
Supersedes "never hardcode the tenure split" for Flexi Hybrid Term Loan: it is offered only for 8 or 9 years total, and the interest-only initial tenure is set by the total — 96 mo → 24 mo initial (2+6), 108 mo → 36 mo initial (3+6). App\Support\Calculators\FlexiHybridTenure is the single source for this; ⚡flexi-hybrid-calculator and lender-comparison-table read it and ignore lender_products.initial_tenure_months / loan_products.default_initial_tenure_months. Change the split only in FlexiHybridTenure::INITIAL_MONTHS_BY_TOTAL_MONTHS.
