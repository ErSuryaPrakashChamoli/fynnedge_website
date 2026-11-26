# 23 — Financial and credit validation report (BV-01 to BV-18)

**For:** Finance, Credit, the Head of Advisory and Compliance, the approvers named for each item.
**Date:** 26 Nov 2026.
**Prepared by:** engineering (Claude).

**This report is evidence, not approval.** All 18 items remain open. Engineering cannot approve any of them.

## 1. Result

| Measure | Result |
| --- | --- |
| Engine and formula version | `FormulaVersion` **1.0.0**. No uncommitted change under `app/Modules/DebtConsolidation/`; the engine was last changed in commit `434ec8e`. |
| Specification match | **827 of 827** figures match in `validation/DC-financial-validation-workbook.xlsx` (an independent Decimal reference implementation; Checks A–D) |
| Re-check today | The engine was re-run on cases V01–V10 into a scratch folder. Its output is **identical** to the preserved `validation/engine-results.json`, so the 827/827 result holds for the code as it stands now. |
| Engine unit tests | 8 files in `tests/Unit/DebtConsolidation/`, plus engine assertions in the feature tests. Results are in `20-REGRESSION-CLOSURE-REPORT.md`. |
| Real-document evidence (historical cases) | **None yet.** BLOCKED by HV-00 (`21-…`, `22-…`). |
| Items approved | **0 / 18** |
| Formula or rule defects found in this review | **None** |

**What "verified" means here:**
- the code does what the specification and the workbook say;
- the rule can be seen in the code and in a test.

It does **not** mean the business has accepted the rule. Acceptance is recorded in the Status column.

## 2. Item-by-item record

How to read the columns:
- **Implementation:** where the rule lives.
- **Evidence held:** what has been checked.
- **Missing evidence:** what the approver still needs.
- **Parent:** the formal approval the item rolls up to (`17-…` §2).

| ID | Rule | Implementation | Evidence held | Missing evidence | Approver | Parent | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| BV-01 | EMI = P·r(1+r)^n / ((1+r)^n − 1); interest rounded half-up to the paisa each month; the final instalment clears the balance | `Finance/Amortisation.php`, `Money.php` | Check A difference 0; `AmortisationTest` | 3 real lender repayment schedules (HV-01) | Credit / Finance | FA-01 | PENDING APPROVAL |
| BV-02 | Flat-rate detection: an implied reducing rate is worked out from the EMI and months. A gap over **1.5 points** raises a flag, and the implied rate is used. | `LiabilityProjector.php`; `defaults.rate_mismatch_tolerance_points` | V10 matches the reference | A real flat-rate NBFC statement (HV-01 coverage) | Credit | FA-01 | PENDING APPROVAL |
| BV-03 | Partial details:<br>• EMI only → months derived<br>• months only → EMI derived<br>• neither → a **60-month** tenure is assumed and flagged | `LiabilityProjector.php`; `defaults.default_tenure_months` | V01 (pl_c) and V06 match | Credit's written acceptance of the 60-month assumption for advice | Credit | FA-01 | PENDING APPROVAL |
| BV-04 | Cards:<br>• a planned payment is paid until the card is cleared<br>• with no planned payment, like-for-like over each option's tenure<br>• FOIR uses **5%** of the balance<br>• savings calculator: the card payment starts at 5% of the balance and is then held fixed each month (§3.2) | `LiabilityProjector.php`; `defaults.revolving_minimum_payment_percent`; savings calculator component | V02 matches; 18 calculator tests, with reference figures (35 months; interest ₹1,49,977.91) | 2 real card statements showing the minimum due % | Credit | FA-01 | PENDING APPROVAL |
| BV-05 | Interest-only debts (OD, CC, gold bullet, invoice, working capital): interest each month on the full balance, with the principal in the last month | `LiabilityProjector.php` (`RepaymentStructure`) | V03 and V04 match | An overdraft statement and a gold-loan bullet schedule | Credit | FA-01 | PENDING APPROVAL |
| BV-06 | Foreclosure estimate = principal + overdue + pending + accrued interest (full closure only) + charge % × principal + fixed fee + **18% GST** on charges + other charges | `ForeclosureEstimator.php`; `defaults.gst_rate_percent` | Check B difference 0; V07 matches; `ForeclosureEstimatorTest` | 5 real foreclosure letters (HV-01); Legal's view on GST | Finance + Legal (GST) | FA-01 | PENDING APPROVAL |
| BV-07 | Part-prepayment: the charge applies only to the amount paid down; no accrued interest; the loan keeps its EMI and finishes sooner | `ForeclosureEstimator.php`, `ConsolidationComparator.php` | V05 matches | Practice confirmed with 2 lenders (some reduce the EMI instead) | Credit | FA-02 | PENDING APPROVAL |
| BV-08 | Foreclosure quotations:<br>• recorded with amount, date and validity<br>• verified by an independent second person, enforced by the system with no exemption (OPS-D1, Owner decision 26 Nov 2026)<br>• expired quotations flagged<br>• the estimate is used until a verified quotation exists | `DebtForeclosureQuotation`, `UpdateClosureStatus` | Workflow tests; SOP-05 in `25-…` | Operations' approval of the written SOP; a trial in the admin on a non-production copy | Operations | OP-01 | PENDING APPROVAL |
| BV-09 | Loan sizing: the smallest whole-rupee loan whose payout, after fee and GST (rounded to the paisa), covers the amount to be paid. The fee is deducted or paid upfront; a shortfall is shown when the sanction is lower. | `ConsolidationComparator::smallestCoveringLoan()` | Check C; brute force over 480,000 amounts (D-23 fix); 827/827 | 3 real sanction letters with disbursal advice (HV-01) | Credit / Finance | FA-02 | PENDING APPROVAL |
| BV-10 | Net financial benefit = total outflow from today on the existing debts − total outflow of the new loan (principal counted once on each side) − upfront cash | `ConsolidationComparator.php` | Breakdown identity exact in tests; Check C | Head of Advisory's agreement that this is how FynnEdge presents savings | Head of Advisory | FA-02 | PENDING APPROVAL |
| BV-11 | Two break-even months:<br>• cost break-even: when cumulative interest saved covers the costs (may never happen)<br>• cash break-even: when the cumulative monthly saving covers the upfront cash ("Month 0" when nothing is paid upfront) | `ConsolidationComparator.php` | Matches the reference | Head of Advisory's agreement on the definitions and the label | Head of Advisory | FA-02 | PENDING APPROVAL |
| BV-12 | FOIR = debt service ÷ income; bands ≤40 / ≤50 / ≤65%. DSCR = (revenue − operating expenses) ÷ debt service; bands ≥1.75 / ≥1.4 / ≥1.2. | `AffordabilityCalculator.php` | Check D; `AffordabilityCalculatorTest` | Partner lenders' FOIR/DSCR norms | Credit | SC-02 | PENDING APPROVAL |
| BV-13 | Historical case validation | `validation/historical/` | Infrastructure complete (`22-…`) | HV-00, then 10–20 cases | Head of Advisory + Credit | HV-01 | **BLOCKED** (HV-00) |
| BV-14 | Suitability score:<br>• weights 30/25/20/15/10 and bands<br>• override: no net benefit → "Not financially beneficial"<br>• scoring config is versioned and activated by a second person | `SuitabilityScorer.php`; `DebtScoringConfig` | Check D; `SuitabilityScorerTest`; V01–V10 bands | Head of Advisory and Compliance review of the bands for reasonableness | Head of Advisory + Compliance | SC-01 | PENDING APPROVAL |
| BV-15 | Not consolidatable now: NPA, settled, written off, disputed, ≥ **90** DPD, in lock-in | `LiabilityClassifier.php`; `defaults.non_performing_days_past_due` | V09 matches | Partner lenders' acceptance rules | Credit | FA-01 | PENDING APPROVAL |
| BV-16 | Route matrix: which debt types each financing route can take over in principle | `config/debt_consolidation.php` `route_liability_types` | Config reviewed; `LiabilityClassifier` tests | Credit review against lender products (`lender-policy/LP-C` P-06) | Credit | LP-02 | PENDING APPROVAL |
| BV-17 | Standard options: PL 14% / 60m / 2%; LAP 10.5% / 120m / 1%; UBL 17% / 48m / 2%; SBL 11.5% / 84m / 1%. Savings calculator defaults: 14% / 3 years / 2%. | Settings (`DebtConsolidationSettings`); calculator component | Values verified in the code (§3.2) | Current partner rate cards | Credit | LP-02 | PENDING APPROVAL |
| BV-18 | A lower EMI never hides a warning. Warnings: higher total cost; lower EMI but higher cost; longer tenure; rate not lower; unsecured debt becoming secured; tax-benefit loss; shortfall; extra borrowing | Engine flags (`RiskFlag`); `DebtPresenter::flagSentence`; calculator copy | Tests (§3.3); warning list in `06-financial-engine.md` §4 | Compliance approval of the wording (English and Hindi), with LA-02 | Compliance | LA-02 | PENDING APPROVAL |

## 3. Areas the mandate singled out

### 3.1 Assumptions (FA-01)

| Assumption | Value in code | Where it can be changed | Changed in this stage? |
| --- | --- | --- | --- |
| GST on foreclosure charges and processing fees | 18% | Debt Consolidation Settings (falls back to `config/debt_consolidation.php`) | No |
| Card minimum payment | 5% of the balance | Settings | No |
| Default tenure when unknown | 60 months | Settings | No |
| Flat-rate tolerance | 1.5 percentage points | Settings | No |
| Non-performing threshold | 90 days past due | Settings | No |
| Maximum projection horizon | 480 months | Config | No |

- Every change to a setting is audit-logged.
- If a value changes after approval, the affected checks must be re-run against the workbook.

### 3.2 Savings calculator (`/calculators/debt-consolidation`)

Checked in `resources/views/components/⚡debt-consolidation-savings-calculator.blade.php`:

| Assumption (mandate) | Implementation | Test |
| --- | --- | --- |
| Card payment: 5% minimum due, held fixed each month | Starts at the `revolving_minimum_payment_percent` setting (5%) × the balance: ₹10,000 on ₹2,00,000. It follows the balance until the visitor edits it, then stays as typed. The engine pays that fixed amount every month until the card is cleared. The disclaimer says: "Your card payment is assumed to stay the same each month." | *starts the card payment at the minimum due and follows the balance until the visitor sets it* |
| New loan interest 14% | `$newRate = 14` | *compares the EMI and the total cost…* (reference figures) |
| New loan tenure 3 years | `$newYears = 3` | same |
| Processing fee 2% (+18% GST, deducted from the loan) | `$feePercent = 2`; GST from settings | same |

**No assumption was changed in this stage.** They remain subject to BV-04 and BV-17.

**Point for Credit (BV-04):**
- The calculator holds the card payment fixed, but a real minimum due falls as the balance falls.
- A fixed payment therefore clears the card faster and more cheaply than paying only the minimum.
- So the calculator's "today" cost is lower than paying the minimum would be, and its savings figure errs on the low side rather than overstating them.
- The disclaimer states the assumption. Credit should confirm it is acceptable.

### 3.3 A lower EMI is never presented as a saving

| Where | How | Evidence |
| --- | --- | --- |
| Savings calculator | The headline reads "Total saving" only when net benefit > 0; otherwise it reads "Extra cost" in the warning colour. The tenure finder separates "Biggest saving" from "Lowest EMI that saves". When no tenure saves, it says "No tenure from 1 to 7 years saves money…". | Tests *never calls a lower EMI a saving when the total cost goes up…* and *says plainly when no tenure saves money* |
| Debt analyser and assessment | "Save ₹X" appears only when net benefit > 0; otherwise "Costs ₹X more". The engine raises the flags `LowerEmiHigherCost` and `HigherTotalCost`. | Engine unit tests; `AssessDebtApplicationTest` |
| Suitability | Override: with no net benefit the band is "Not financially beneficial", whatever the other dimensions score | `SuitabilityScorerTest`; Check D |
| Page copy | "Not always, and a lower EMI alone does not prove it…"; "Does consolidation always reduce what I pay? No…" | Content of the DC pages migration |

### 3.4 Four concepts kept distinct

| Concept | What it is | Where | What the customer sees | Never implies |
| --- | --- | --- | --- | --- |
| 1. Financial suitability | Engine score (0–100) and band | `SuitabilityScorer` | The score, with the disclaimer "…It is not a loan approval, an offer or a guarantee of eligibility." | Approval or eligibility |
| 2. Repayment affordability | FOIR or DSCR before and after, with bands | `AffordabilityCalculator` | Percentages and bands | Lender acceptance |
| 3. Lender eligibility (indicative) | "Potential match / More information needed / Does not match", tested against an **approved** lender policy | `LenderMatcher` | **Nothing directly.** Only lenders an advisor has shortlisted, shown as "Your advisor suggests…" with "I understand that [lender] alone decides whether to approve the loan". | Approval |
| 4. Lender approval | The lender's own decision, recorded by staff with evidence | `DebtLenderApplication` / `UpdateLenderApplicationStatus` | Status of the lender application | — |

- Reports state: "Financial suitability, repayment affordability and lender eligibility are separate: only a lender decides eligibility."
- No code path turns a score or a match into an approval status.

### 3.5 Other areas

- **Past-due treatment.** Overdue amounts and pending instalments are added to what must be paid at closure. Accounts at ≥90 DPD are "not consolidatable now" (BV-15). Covered by V08 and V09.
- **Foreclosure quotations.** A verified quotation replaces the estimate when one exists. Expired quotations are flagged. See BV-08 and SOP-05.
- **Lender matching explanations.** Every rule produces a reason line, for example "FOIR after consolidation 38.2%; policy maximum 50%". Missing information is listed separately. See `lender-policy/LP-B`.
- **Customer warning wording.** All warnings come from one list (`RiskFlag` → `DebtPresenter::flagSentence`), in English and Hindi. Approval is BV-18, together with LA-02.
- **Lender eligibility and lender policies.** No lender policy is configured yet, and none was invented (`lender-policy/LP-B`).

## 4. Documentation notes (no code change)

1. **Where BV-07 and BV-08 sit.** BV-07 is tracked under FA-02 and BV-08 under OP-01, as reconciled in `17-…` §2. Report `16-…` is preserved unchanged.
2. **Savings calculator disclaimer.** The wording is shown to customers, so it needs BV-18 / LA-02 approval like every other warning.

## 5. What each approver needs to do

| Approver | Items | First action |
| --- | --- | --- |
| Credit | BV-02, 03, 04, 05, 07, 12, 15, 16, 17 | BV-12 / SC-02 and BV-16 can start now (no dependency) |
| Finance | BV-01, 06, 09 (with Credit) | Review Checks A–C now; final sign-off after HV-01 |
| Legal | BV-06 (GST part) | With Finance |
| Head of Advisory | BV-10, 11, 14; HV-01 | BV-10 and BV-11 can start now |
| Compliance | BV-14, BV-18 | BV-18 together with LA-02 |
| Operations | BV-08 | Approve SOP-05 (`25-…`) |

Record each decision with name, date and reference:
- in `17-BUSINESS-APPROVAL-TRACKER.md` §4;
- in the workbook's "Business validation" sheet.
