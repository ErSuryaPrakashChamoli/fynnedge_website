# 14 — Calculator and lender-matching specifications

## Calculator suite (`/debt-consolidation/calculator`)

One Livewire component (`⚡debt-consolidation-calculator`); the visitor enters loans once and switches views. Every figure comes from `DebtConsolidationEngine::assess()` (see `06-financial-engine.md`); nothing is computed in the view. Tabs shown are chosen in settings.

| Calculator | Inputs | Outputs |
| --- | --- | --- |
| 1 Consolidation | Loans (type, lender, outstanding, rate, EMI/payment, months, foreclosure %/fixed, other charges, overdue, pending EMI, standing, full/partial/keep); up to 3 proposed options (rate, tenure, fee %, fee deducted?, charges financed?) | Total existing outstanding, existing total EMI, remaining repayment, existing interest, loan amount, proposed EMI, proposed total repayment, proposed interest, fees and charges, monthly cash-flow change, net saving, repayment period, average rate, suitability score and band, warnings |
| 2 Foreclosure cost | Same loans | Per loan and total: principal, dues (overdue + pending + accrued), charges + GST, amount to close; quotation validity guidance |
| 3 EMI & savings comparison | Same | Side by side: keep vs each option — monthly outgo, total repayment, interest, fees, months, net saving, suitability |
| 4 Affordability | + income, other income, living expenses, other obligations (individual) / revenue, operating expenses (business) | FOIR (or DSCR) today and after, band, debt service, disposable income / cash left |
| 5 Break-even | Same | Month in which interest saved covers all costs, whether it holds to the end, costs, yearly cumulative chart |
| 6 Portfolio summary | Same | Total debt, count, monthly repayments, weighted rate, secured/unsecured, remaining interest, largest lender share, maturity buckets |

Validation: rows without a positive outstanding are ignored; numbers are clamped to sane ranges (rate 0–60 %, months 1–480, fee 0–10 %); "Save these figures" validates fully. Snapshots: stored only on "Save these figures and get a free assessment" (kind `calculator`) with the debts, options and income entered, then linked to the case the visitor opens or resumes after the OTP. The button appears only while the assessment page is published and the service accepts that applicant type. Disclaimers from settings are shown under the results.

## Lender matching

Policies (`debt_lender_policies`) attach to existing lender offers. Only **Active** policies inside their effective dates, for active offers of active lenders, are used; the newest version per offer wins. `MatchLenders` takes a fresh snapshot, then `LenderMatcher` checks:

| Check | Source | Fails when |
| --- | --- | --- |
| Applicant type | policy | type not accepted |
| Liability types | policy | any debt being consolidated is of a type the lender does not take over (named) |
| Existing lenders | policy | a debt is with an excluded lender, or with this lender while it refuses its own loans |
| Days past due | policy | worst DPD above the maximum |
| Loan amount | policy (else offer) | indicative loan (engine, policy mid-rate, max tenure, fee) outside min–max |
| FOIR / DSCR | policy | after-consolidation figure on the policy's terms above max / below min |
| Income, vintage, turnover, credit score | policy | below minimum (credit score is self-declared and labelled so) |
| Collateral and LTV | policy | no accepted asset, or loan ÷ free value above max |
| Lender eligibility rules | the offer's active rule set in Eligibility Rules (reused unchanged) | a mandatory rule fails |

Missing data (income, score, collateral…) makes the result **More information needed** instead of a guess. Outcome: any failure → Does not match; else any missing → More information needed; else **Potential match**. Each result stores the reasons, the missing items, the indicative amount and rate range, and the snapshot it used. Advisors record their own review (shortlist / not suitable, with reasoning); a changed outcome resets the review. A match is never shown or described as an approval or offer.

## Debt consolidation savings calculator (/calculators/debt-consolidation)

This calculator sits in the site's general Calculators section. It is upgraded on 26 Nov 2026 after research into leading calculators (break-even, payoff timeline, tenure trade-off, the minimum-due trap, FOIR norms).

**Inputs**
- Cards: balance, rate, and what is paid each month. The payment starts at the minimum due from settings (5%) and follows the balance until the visitor edits it.
- Loans: balance, rate, months left, foreclosure %.
- New loan: rate, tenure (1–7 years), fee %.
- Optional net income.

**How it calculates.** ONE engine run covers every tenure from 1 to 7 years.

**What it shows**
- A total-cost verdict: "Total saving" only when the net benefit is positive, otherwise "Extra cost".
- The EMI change, the debt-free date before and after, and the fee break-even month.
- An exact breakdown: interest today − interest on the new loan − fees, GST and foreclosure charges = net.
- A tenure finder: biggest saving, best below today's EMI, and the lowest EMI that still saves, each with a "Use" button.
- A year-by-year cumulative chart.
- Each debt kept as is: months to clear and interest left. This exposes the minimum-due trap.
- FOIR before and after, with 40% and 50% markers.
- Computed plain-language insights, and the engine's warnings.

**Sharing.** A link of the inputs reopens the same figures; values from the link are range-checked.

**Next step.** The button leads to the free assessment while that page is live, otherwise to an advisor. The calculator itself is always available.
