# 22 — Historical validation execution report (BV-13 / HV-01)

**For:** the Head of Advisory and Credit (owners), Finance, and the Owner.
**Date:** 26 Nov 2026.
**Status:** **BLOCKED.** HV-00 (Legal/DPO permission) is not recorded, and no historical documents have been provided. **No historical case has been processed.**

## 1. Summary

| Item | Status | Evidence |
| --- | --- | --- |
| HV-00 Legal/DPO permission | **PENDING APPROVAL** | Decision pack `21-HV-00-DECISION-PACK.md`; workbook gate shows "CLOSED" |
| Execution infrastructure | **COMPLETE** | §2 |
| Cases collected | 0 of 10–20 | Case register empty |
| Comparisons made | 0 | — |
| Discrepancies (D1–D5) | none recorded, because nothing has been run | — |
| Unresolved engine defects (D4) | none known from historical validation; **not yet tested against real lender documents** | — |
| HV-01 / BV-13 sign-off | **BLOCKED** (needs HV-00, then documents, then execution) | — |

No historical case was invented, and no supporting document was created. The only engine run performed was a **self-test of the tool**. It used acceptance-stage case V01 from `validation/cases.json`, which is a specification test case, not a historical case. The run happened in a temporary folder outside the repository and was then deleted (§3).

## 2. Infrastructure delivered

All files are in `docs/debt-consolidation/validation/historical/`. They are empty templates and tools with no case data.

| Mandate item | Where | Notes |
| --- | --- | --- |
| Anonymised case register | Workbook → *Case register* | Rows HC-01 to HC-20; status; coverage tags; advisor; second checker; dates; C11 reviewer |
| HC-01 to HC-20 case templates | Workbook → *Case inputs* / *New loan inputs*; `hc-pack-template.json` | One input row per debt; one new-loan row per case; JSON pack template for the engine run |
| Input data templates | Same | Field list follows procedure §5 and §8.1 |
| Second-checker verification fields | Workbook → *Second check* | Per input row: every figure matches? redaction complete? corrections; checker ≠ preparer |
| Financial comparison output | `hc-harness.php` → `hc-engine-extract.csv`; workbook → *Comparison* | The harness emits engine values for C1–C11; the sheet computes difference, tolerance (₹1; months exact; FOIR 0.01) and within/not within; red highlight when a row is outside tolerance and unclassified |
| Discrepancy register | Workbook → *Discrepancies* | HD-001 to HD-200; class D1–D5; size and effect; decision; decided by; status |
| Evidence tracking | Workbook → *Evidence* | Document type, held, restricted-folder location reference, redaction checker, delete-by date |
| Approval fields | Workbook → *Approvals*, *HV-00 gate* | Team names, preparer, checker, engineer, Credit review, Head of Advisory approval |
| Final validation summary | Workbook → *Summary* | Counts of M, D1–D5, unresolved D4, D3/D5 without decisions, coverage; acceptance-criteria result |

**Coverage** (sheet *Coverage*). It follows procedure §3 and adds the two mandate items that procedure §3 did not list separately: **full consolidation** and **partial consolidation**.
- Personal loans: 3 cases.
- Credit cards: 2.
- Business term loan + CC/OD: 2.
- Secured debt: 1.
- Part-prepayment: 1.
- Flat-rate NBFC: 1.
- Overdue at closure: 1.
- Not beneficial: 1.
- Full consolidation: 1.
- Partial consolidation: 1.
- Foreclosure letters with breakdown: at least 5.
- Sanction letter with disbursal advice: at least 3.

The *Summary* only shows "MET" when **all** of these hold (procedure §9):
- HV-00 is open;
- at least 10 cases are complete;
- every coverage minimum is met;
- no out-of-tolerance row is left unclassified;
- every C11 review is signed;
- no D3 or D5 lacks a decision;
- **no unresolved D4**.

## 3. Verification of the tools

| Check | Result |
| --- | --- |
| Workbook formulas | 1,845 formulas; recalculated in LibreOffice; 0 errors |
| Workbook logic (scratch copy with dummy values, then deleted) | Gate opens only with 9 approved decisions that each carry a name, date and reference. ₹0.50 difference → within; ₹2 → not within; months differ by 1 → not within; C11 → "review"; an open D4 → "D4 unresolved = 1"; acceptance not met. |
| Harness refusals | Refuses with no HV-00 reference; with a `customer_name` field; with a case code other than HC-NN; with a pack inside the website repository. Each exits with code 1. |
| Harness output | On a copy of V01 relabelled HC-01 (self-test only): engine output **identical** to `validation/engine-results.json` for V01; 36 comparison values extracted |
| Preserved evidence | `16-…` md5 `c23a25c5…` and `validation/DC-financial-validation-workbook.xlsx` md5 `62b04dc4…` are unchanged. The harness and the builder write only into their own folder or the given output folder. |

## 4. What happens next (owners)

| Step | Owner | Depends on |
| --- | --- | --- |
| 1. Decide HV-00 (9 points, retention option) | Legal / DPO | — |
| 2. Name the team; create the restricted folder; agree dates | Head of Advisory, Operations, Owner | 1 |
| 3. Select 10–20 cases against the coverage table; redact; second-check the redaction | Head of Advisory, advisors, Credit | 2 |
| 4. Enter inputs; second check | Advisors, Credit | 3 |
| 5. Build the case pack and run the harness; paste the extract | Engineer | 4 |
| 6. Classify, decide D3/D5, fix any D4 (engineering, with a regression run and re-run) | Credit, Head of Advisory + Finance, Engineering | 5 |
| 7. Credit review, Head of Advisory approval; record HV-01 / BV-13 | Credit, Head of Advisory | 6 |

Engineering will run step 5 and fix any D4 when the case pack is supplied. Nothing can start before step 1.

## 5. Effect on release

- HV-01 / BV-13 is a business validation gate (B in `30-…`), not a deployment prerequisite.
- The code can be deployed with the service **off** without it.
- The service must **not** be switched on for live customer applications until HV-01 is signed, or until Finance and Credit formally decide otherwise on alternative evidence (`21-…` §10).
