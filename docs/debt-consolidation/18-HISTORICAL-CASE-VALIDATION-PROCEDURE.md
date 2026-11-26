# 18 — Historical case validation procedure (BV-13 / HV-01)

**For:** the Head of Advisory and Credit (owners), the advisors entering cases, and the engineer running the engine. **Status:** not started.

## 1. Purpose and what this proves

The validation workbook shows the engine computes exactly what its specification says (827 of 827 figures). This procedure tests something different: whether the specification matches **real lender documents** for real past cases.

The results are the evidence that Finance and Credit need before they sign FA-01 (BV-01, BV-06) and FA-02 (BV-09). Nothing here is an approval by itself. The Head of Advisory and Credit sign the final summary (§9).

## 2. Before starting

| Step | Who | Done |
| --- | --- | --- |
| HV-00: Legal/DPO confirms in writing that **redacted** documents from past cases may be used for internal validation, and how long the validation pack is kept | Legal / DPO | ☐ |
| Name the owner, one or two advisors, a second checker (Credit), and the engineer | Head of Advisory | ☐ |
| Create a restricted folder for the validation pack (named people only; not shared drives open to all staff; not inside the website) | Operations | ☐ |
| Agree the target dates | Owner | ☐ |

**Never do any of the following:**
- run validation cases on the production site;
- create validation cases as customer cases in the admin. The case form requires a customer-consent declaration, so this would create a false consent record;
- upload source documents to the website.

## 3. Choosing the cases (10–20)

Choose closed cases where FynnEdge holds the lender's own documents. Cover at least:

| Coverage | Minimum cases | Why |
| --- | ---: | --- |
| Individual with 2+ personal loans (statements with EMI and months left) | 3 | BV-01, BV-02, BV-03 |
| Credit cards with statements showing balance, rate and minimum due | 2 | BV-04 |
| Business: term loan plus overdraft / cash credit | 2 | BV-05, BV-12 (DSCR) |
| Secured debt moved to LAP or a secured business loan | 1 | BV-16, warnings |
| Partial pay-down (part-prepayment) | 1 | BV-07 |
| Flat-rate NBFC loan | 1 | BV-02 |
| Overdue / pending instalment at closure | 1 | BV-06 |
| A case where consolidation did **not** go ahead or did not save money | 1 | BV-10, BV-14 |
| **Lender foreclosure letter with a breakdown** (principal, interest, charges, GST) | in **at least 5** cases | BV-06 |
| **Sanction letter and disbursal advice** of the consolidation loan | in **at least 3** cases | BV-09 |

**Source documents for each case** (tick in the record sheet):
- statement or repayment schedule for each existing loan;
- credit card statements;
- foreclosure letters or quotations, with their date and validity;
- the sanction letter and disbursal advice of the new loan;
- closure payment proof and NOC;
- income documents (only if FOIR or DSCR is checked).

## 4. Anonymisation rules

| Data | Rule |
| --- | --- |
| Customer name, PAN, Aadhaar, mobile, email, address, date of birth, employer name, photos, signatures | **Remove.** Never enter. Redact on every document copy. |
| Loan, card, bank-account numbers, UTRs, customer IDs, application numbers | **Remove** (full redaction; last four digits are not needed) |
| Lender name, product type, branch city | **Keep.** Needed to compare lender practice. |
| Amounts, rates, tenures, EMIs, charges, GST | **Keep exactly.** The ₹1 tolerance depends on it. |
| Statement and letter dates | Keep month and year. Keep the day only where the document's validity or interest depends on it. |
| Income | Keep only if FOIR/DSCR is compared; otherwise omit. |

- Each case gets a code: **HC-01 … HC-20**. The key linking codes to real files stays with the Head of Advisory only, outside the validation pack.
- At the end, delete or keep the pack under the period Legal/DPO set in HV-00.

## 5. Data entry (advisor → engineer)

Advisors fill a **case input sheet** per case (template in §8.1), copying figures from the documents exactly. The engineer enters them into the validation harness (`validation/engine-harness.php`, on a copy of the case file, never on production) and returns the engine's figures into the comparison sheet (§8.2). A second person checks every input against the document before the run.

**For each existing loan or card**

| Sheet field | Copy from | Notes |
| --- | --- | --- |
| Debt type | Statement | personal loan, credit card, business term loan, overdraft, gold loan, LAP… |
| Lender | Statement | Name only |
| Outstanding principal (₹) | Statement / foreclosure letter, at the **closure date** | Principal only, without interest or charges |
| Interest rate (% a year) | Statement / sanction letter | As stated. For flat-rate loans, enter the flat rate; the engine detects it (BV-02). |
| EMI or monthly payment (₹) | Statement | For cards: the payment actually made each month |
| Months remaining | Repayment schedule | Count of instalments left at the closure date |
| Overdue amount, instalment due before closure, accrued interest (₹) | Foreclosure letter | Only if the letter lists them |
| Foreclosure charge %, fixed fee, other charges (₹) | Loan agreement / letter | **Do not enter the letter's total as a quotation.** Leave "quoted closure amount" empty, so the engine's own estimate is tested against the letter. |
| Full or part closure, amount paid down | Closure records | |

**For the consolidation loan (from the sanction letter):** rate, tenure in months, processing fee % and fixed fee, whether the fee was deducted from the payout, whether foreclosure charges were paid from the loan, and the sanctioned amount (entered as the sanctioned amount only where the lender sanctioned a fixed figure).

**Date note.** The engine runs as of the day it is used, so an old quotation shows as "expired" and a historical lock-in may show as ended. Neither changes any amount compared here. Note them; don't treat them as discrepancies.

## 6. What is compared

| # | Comparison | Document value | Engine value | Tolerance | BV item |
| --- | --- | --- | --- | --- | --- |
| C1 | Amount to close each loan | Foreclosure letter total | Foreclosure estimate total | **₹1** | BV-06 |
| C2 | Charge and GST on each closure | Letter's charge and GST lines | Engine charge and GST | **₹1** each | BV-06 |
| C3 | Remaining repayment of each loan | Sum of remaining instalments in the lender's schedule | Engine remaining repayment | **₹1** | BV-01, BV-02, BV-03, BV-05 |
| C4 | Remaining interest of each loan | Lender schedule | Engine remaining interest | **₹1** | BV-01 |
| C5 | New loan amount | Sanction letter | Engine loan amount | **₹1** | BV-09 |
| C6 | Processing fee, GST on fee, net disbursal | Sanction letter / disbursal advice | Engine figures | **₹1** each | BV-09 |
| C7 | New EMI | Sanction letter / schedule | Engine EMI | **₹1** | BV-01 |
| C8 | Card: months to clear at the payment made | Statement's "time to clear paying minimum" where shown | Engine months | exact (months) | BV-04 |
| C9 | Part-prepayment: balance and remaining EMIs after pay-down | Lender's revised schedule | Engine residual schedule | **₹1** | BV-07 |
| C10 | FOIR / DSCR (only where the lender computed one) | Lender's credit note | Engine value | **0.01 percentage point** / **0.01** | BV-12 |
| C11 | Verdict and score: reasonableness | Advisor's judgment of the real outcome | Net benefit, band and warnings | No numeric tolerance; reviewed and signed | BV-10, BV-14, BV-18 |

Money is compared to the rupee: a difference of ₹1.00 or less is a match. Every larger difference gets a class (§7), even if it looks small.

## 7. Discrepancy classes

| Class | Meaning | Typical example | Action | Decided by |
| --- | --- | --- | --- | --- |
| **M** | Match (≤ ₹1) | — | None | — |
| **D1** | Data-entry error | Figure copied wrongly | Correct the input, have it re-checked, re-run | Second checker |
| **D2** | Document gap or ambiguity | Letter gives only a total, with no breakdown | Ask the lender or find a better document; if unavailable, exclude this comparison with a note | Credit |
| **D3** | Lender practice differs from the engine's method | Interest by actual days rather than monthly; EMI rounded up to the next rupee; charge on principal plus interest; broken-period interest | **Business decision**: accept as a known variance (record its size and effect on customers) **or** request a method change (new formula version, re-validation) | Head of Advisory + Finance |
| **D4** | Engine defect | Engine result differs from its own specification for the inputs given | Engineering fix, regression run, re-validate the affected cases. **Blocks sign-off.** | Engineering, then re-check |
| **D5** | Not modelled | Bundled insurance, pre-EMI interest, lender-specific fees | Decide: add an input, accept, or exclude | Head of Advisory |

## 8. Record sheets

### 8.1 Case input sheet (one per case)

| Field | Value |
| --- | --- |
| Case code | HC-__ |
| Applicant type | Individual / Business |
| Coverage tags (§3) | |
| Documents held (tick) | ☐ Statements ☐ Card statements ☐ Foreclosure letters ☐ Sanction letter ☐ Disbursal advice ☐ NOC ☐ Income |
| Redaction checked by / date | |
| Debts entered (one row each: type, lender, principal, rate, EMI, months, overdue, pending, accrued, charge %, fixed fee, other, mode, paid-down amount) | |
| New loan entered (rate, months, fee %, fixed fee, fee deducted Y/N, charges financed Y/N, sanctioned amount) | |
| Entered by / date | |
| Inputs checked against documents by / date | |

### 8.2 Comparison sheet (one row per comparison)

| Case | # | Item | Document value (₹) | Engine value (₹) | Difference (₹) | ≤ ₹1? | Class | Explanation | Action / owner | Resolved (date) |
| --- | --- | --- | ---: | ---: | ---: | --- | --- | --- | --- | --- |
| HC-01 | C1 | Loan 1 closure total | | | | | | | | |

### 8.3 Summary (for sign-off)

| Measure | Result |
| --- | --- |
| Cases completed / coverage met (§3) | __ / __ ; ☐ yes |
| Comparisons made | |
| Matches (M) | |
| D1 corrected | |
| D2 excluded, with reasons | |
| D3 method differences, each with a recorded decision | |
| D4 engine defects (**must be 0 unresolved**) | |
| D5 not modelled, each with a recorded decision | |

## 9. Acceptance criteria and sign-off

The parallel run is accepted when **all** of the following hold:
- at least 10 cases are complete and the coverage in §3 is met;
- every comparison is M, or has a class with a recorded resolution;
- there are **no unresolved D4**;
- every D3 and D5 has a named business decision;
- the C11 reasonableness review is signed for every case.

| Role | Name | Decision | Date | Signature / reference |
| --- | --- | --- | --- | --- |
| Prepared by (advisor) | | | | |
| Inputs checked by (second person) | | | | |
| Engine run by (engineer) | | | | |
| Reviewed by (Credit) | | Accept / Accept with conditions / Reject | | |
| Approved by (Head of Advisory) | | Accept / Accept with conditions / Reject | | |

On approval, record the outcome against **HV-01** and **BV-13** in `17-BUSINESS-APPROVAL-TRACKER.md` and in the workbook's "Business validation" sheet. Attach the summary and the comparison sheets to the validation pack. Any D3 decision that changes a method opens a new formula version (`06-financial-engine.md` §9). That version then needs its own re-validation before the affected BV items can be signed.
