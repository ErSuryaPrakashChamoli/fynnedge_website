# 06 — Financial engine specification

**Code:** `app/Modules/DebtConsolidation/Services/Finance/` · **Formula version:** `1.0.0` (`FormulaVersion::CURRENT`) · **Tests:** `tests/Unit/DebtConsolidation/` (every expected figure was derived independently — by hand or with a separate Python `Decimal` implementation — never with the engine's own code).

The engine is pure PHP: no database, clock or config reads. All inputs arrive in a `CaseInput` (liabilities, scenarios, finances, `Assumptions` incl. the as-of date, `ScoringConfig`, route matrix). `DebtConsolidationEngine::assess()` returns a `CaseAssessment`. Both serialise to arrays; a snapshot stores `inputs`, `results`, the formula version and an input hash, and `CaseInput::fromArray(inputs)` reproduces identical results (pinned by a test).

## 1. Money and rounding

- Amounts are rupees with paise. Every amortisation period is rounded before the next one starts (interest first, then the balance), and the final instalment clears the balance exactly — as on a lender's schedule. Totals are sums of the rounded periods (summed in integer paise).
- Rounding is **half-up** ("commercial"). PHP 8.4+ `round()` rounds a float's exact binary value, so ₹2,029.755 held as 2029.7549999… would round down. Interest and percentages at rates with up to four decimals are therefore computed in integer paise (`Money::percentage`, `Amortisation::interest`). Only rates solved by bisection use floating point; they never sit exactly on a half-paisa.
- EMI: `P·i·(1+i)^n / ((1+i)^n − 1)` with `i = annual% / 1200`, rounded to the paisa; `P/n` at 0 %. Identical to the site's `EmiCalculator` (pinned).

## 2. Liability projection (cost of keeping a debt)

| Structure | Known | Projection |
| --- | --- | --- |
| Amortising (EMI) | EMI **and** remaining months | Contractual payments. The implied reducing rate is solved (bisection, 200 fixed steps). If the stated rate agrees within 1.5 points it is the contract rate and drives the schedule; otherwise the implied rate is used and **Rate mismatch** is flagged (typical of a quoted flat rate). EMI × months below the outstanding → **Loan details do not add up**. |
| | EMI only | Pay the EMI at the stated rate until cleared; EMI ≤ monthly interest → **Payment does not reduce the debt**, projected over the scenario tenure with the grown balance settled at the end. |
| | Months only | EMI derived from rate and months. |
| | Neither | Scenario tenure assumed → **Repayment plan assumed**. |
| Revolving (card) | Planned payment | Pay it until cleared (or flag as above). |
| | No payment | Amortised over the scenario tenure (like-for-like) and flagged; for FOIR the obligation is **5 %** of the balance (setting). |
| Interest only (gold bullet, CC, OD, invoice) | Months (or scenario tenure, flagged) | Interest monthly, principal in the last month. |

Partial pay-down: amortising loans keep the EMI and finish sooner (lenders' default for prepayments); revolving keeps its payment; interest-only keeps its term.

## 3. Foreclosure (closure amount)

`total = principal closed + overdue + pending instalment + accrued interest (full closure only) + charge + GST on charge + other charges`, where `charge = principal closed × charge% + fixed fee` and GST defaults to 18 %. A part-payment pays the overdue/pending dues and is charged only on the amount paid down. For a full closure, a lender **quotation** replaces the estimate; it counts as verified only when staff verified it and it is still within its validity date. Lock-in until a future date is flagged.

## 4. Existing vs proposed (no double counting)

For the liabilities in scope (full or partial):

```
existing total  = overdue + pending dues paid now + Σ remaining payments
proposed total  = upfront cash + Σ new-loan payments + Σ payments on balances left after partial closures
net benefit     = existing total − proposed total
```

Principal appears once on each side: in the old payments on one side, in the new loan's repayment on the other. Liabilities kept as they are appear on neither side (they cancel), but do count in affordability.

**Loan amount.** Amount to finance `R` = principal closed + dues (+ closure charges when financed). If the lender deducts the processing fee and its GST from the payout, the loan is grossed up to the **smallest whole-rupee amount whose payout covers R**, the payout being `L − fee − GST on fee` with the fee (`L·p + F`) and its GST each rounded to the paisa. That amount is `(R + F·(1+t)) / (1 − p·(1+t))` rounded up to the rupee, or one rupee either side of it when the paisa rounding of the fee decides (p fee rate, F fixed fee, t GST). Otherwise `L = R` (rounded up) and the fee is upfront cash. A sanctioned amount overrides `L`; a gap is a **shortfall** paid upfront, an excess is flagged as **extra borrowing**.

**Outputs.** Total existing outstanding, existing total EMI, remaining repayment cost, existing interest, proposed EMI, proposed total repayment, proposed interest, monthly cash-flow difference, net financial benefit, benefit %, total consolidation costs, tenure change, weighted existing rate, rate improvement, per-liability lines and a yearly cumulative comparison.

**Break-even.** *Cost break-even*: first month in which cumulative interest saved ≥ all consolidation costs (charges, taxes, fees, other costs — financed or not), plus whether that still holds when both repayments end. *Cash break-even*: first month in which cumulative cash paid under the proposal is no more than under the existing plan.

**Warnings never hidden by a lower EMI:** consolidating costs more (net < 0), lower EMI but higher total cost, repayment period longer, proposed rate not lower, unsecured debt would become secured (secured route), possible loss of tax benefit (home/education loans), shortfall, extra borrowing.

## 5. Affordability

- **Individuals:** `FOIR % = (all monthly debt obligations + other fixed obligations) ÷ (net income + other income) × 100`, before and after (after = kept liabilities + payments on partial balances + new EMI). Disposable income = income − living expenses − obligations. Bands (default): ≤ 40 % comfortable, ≤ 50 % manageable, ≤ 65 % stretched, above: not affordable.
- **Businesses:** `DSCR = (average monthly revenue − operating expenses excl. loan repayments) ÷ monthly debt service`. Bands: ≥ 1.75 comfortable, ≥ 1.4 manageable, ≥ 1.2 stretched, below: not affordable.

## 6. Consolidation Suitability Score

Five dimensions, each 0–100 on a straight line between two thresholds, weighted (defaults per mandate):

| Dimension | Weight | Measure | 0 at | 100 at |
| --- | ---: | --- | --- | --- |
| Total financial benefit | 30 % | net benefit ÷ existing total | ≤ 0 % | ≥ 10 % |
| Repayment affordability | 25 % | proposed FOIR / DSCR | FOIR ≥ 70 % / DSCR ≤ 1.0 | FOIR ≤ 40 % / DSCR ≥ 1.75 |
| Interest cost improvement | 20 % | weighted old rate − new rate | ≤ 0 pts | ≥ 8 pts |
| Consolidation cost efficiency | 15 % | all costs ÷ new loan | ≥ 6 % | ≤ 1 % |
| Repayment profile | 10 % | extra months | ≥ 36 | ≤ 0 |

Bands: ≥ 70 *Potentially suitable*; ≥ 50 *Borderline — needs advisor review*; below *Low suitability*; **any scenario with no net benefit is "Not financially beneficial" whatever its score**. Critical flags and overdue/securing/lock-in/inconsistent-data flags always require advisor review. Weights and thresholds are versioned per applicant type in `debt_scoring_configs`; the active version is stamped on each result.

Three separate answers are always shown separately: financial suitability (this score), repayment affordability (FOIR/DSCR band) and lender eligibility (lender matching). None is a loan approval.

## 7. Consolidatable now?

Every liability is recorded and counts in the portfolio. It is kept out of scenarios (and flagged) when it is non-performing, settled, written off, disputed, ≥ 90 days past due, or inside a foreclosure lock-in. Routes that can take each liability type over in principle come from `config/debt_consolidation.php` (`route_liability_types`); lenders narrow this further in their policies.

## 8. Portfolio summary

Total exposure, count, total monthly obligation, weighted average rate (by outstanding, using the rate each projection used), highest rate, secured vs unsecured, overdue total and count, remaining repayment and interest, exposure by type and by lender, largest lender share, concentration (Herfindahl index of lender shares, 0–10,000), maturity buckets (≤ 12, 13–36, 37–60, 61–120, > 120 months).

## 9. Change log

| Version | Date | Change |
| --- | --- | --- |
| 1.0.0 | 25 Nov 2026 | First release. |
| 1.0.0 | 25 Nov 2026 | Pre-release correction found in acceptance (no snapshot existed in production): the grossed-up loan is the smallest whole rupee whose paisa-rounded payout covers R. Rounding the formula to the paisa first could leave the payout a paisa short (a false "shortfall" warning) in about 0.15 % of amounts. Validated in `validation/`. |
