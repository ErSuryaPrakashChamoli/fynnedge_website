# 17 — Business approval tracker: Debt Consolidation

**Stage:** Business Validation and Sign-off · **Opened:** 26 Nov 2026 · **Technical status:** Technically ready, business approvals pending (`16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md`).

**For:** the owner and the approvers in Legal, Compliance, Finance, Credit, Head of Advisory and Operations.

> **Technical results are not approvals.**
> - The engine matched the independent reference on all 827 figures. That proves the code calculates what the specification says.
> - It does **not** prove the specification is how FynnEdge, its lenders and the regulator require it to be.
>
> Every row below stays **Pending** until the named approver records a decision, a date and a reference here. No row may be marked approved because a test passes.

## 1. Summary

| Group | Items | Approved | Pending |
| --- | ---: | ---: | ---: |
| Formal approvals (report 16 §6) | 13 | 0 | 13 |
| Calculation and business validation items (workbook BV-01–BV-18) | 18 | 0 | 18 |
| Deployment prerequisites (not approvals; §6) | 5 | — | PR-01 done; 4 open |

**Release-candidate stage (26 Nov 2026).** All gates are now in one matrix, `30-RELEASE-GATE-MASTER-REGISTER.md`, in seven groups:
- A: business approvals;
- B: BV items;
- C: HV-00;
- D: technical gates incl. CO-01;
- E: PR-01 to PR-05;
- F: DP-01;
- G: go-live.

IDs are unchanged. This tracker remains the place where business decisions are recorded. The decision packs are:
- `21-…` (HV-00);
- `23-…` (BV items);
- `24-…` (Legal);
- `25-…` (Operations);
- `lender-policy/` (LP-01).

How to record a decision:
1. Fill in **Decision**, **Signed by** (name and role), **Sign-off date** and **Reference** (email, minutes or document) in the row.
2. Decision values: *Approved* · *Approved with changes* (list the changes and who makes them) · *Rejected* (reason) · *Deferred* (new date).
3. A BV row is approved in the workbook's "Business validation" sheet **and** here. The two must agree.

## 2. Reconciliation: 13 approvals ↔ 18 BV items

Report 16 and the workbook are preserved unchanged. This table is where they are joined.

| Formal approval (report 16) | BV items it covers | Reconciliation note |
| --- | --- | --- |
| LA-01 Legal: regulatory position | — | Not a calculation |
| LA-02 Consent and disclaimer wording | **BV-18** | **Added here.** Report 16 does not reference BV-18 (warning wording, approver Compliance). Customer-facing wording belongs with LA-02. |
| LA-03 Fee wording | — | Not a calculation |
| FA-01 Financial assumptions | BV-01, BV-02, BV-03, BV-04, BV-05, BV-06, BV-15 | Report 16 cites "BV-01–BV-07, BV-15". BV-07 is tracked under FA-02 (see that row). |
| FA-02 Calculation methods | **BV-07**, BV-09, BV-10, BV-11 | Report 16 lists part-prepayment in FA-02's scope and BV-07 in FA-01's range. **One owner: FA-02.** |
| SC-01 Suitability score | BV-14 | — |
| SC-02 Affordability bands | BV-12 | — |
| LP-01 Lender policies | — | Uses the route matrix approved in BV-16 |
| LP-02 Indicative options and route matrix | BV-16, BV-17 | — |
| HV-01 Historical case validation | BV-13 | Procedure: `18-HISTORICAL-CASE-VALIDATION-PROCEDURE.md` |
| OP-01 Operational readiness | **BV-08** | **Added here.** Report 16 does not reference BV-08 (quotation verification procedure, approver Operations). |
| CO-01 Code review and commit | — | Not a calculation |
| DP-01 Deployment authorisation | — | Not a calculation |

All 18 BV items are covered exactly once: FA-01 has 7, FA-02 4, SC-01 1, SC-02 1, LP-02 2, HV-01 1, OP-01 1 and LA-02 1.

**Scope added after acceptance.** The Debt Consolidation Savings Calculator (`/calculators/debt-consolidation`, requirement 20) was built after report 16. It uses the same engine, but brings its own public-facing defaults:

- a card payment that starts at the 5% minimum due and stays fixed each month;
- a default new loan of 14% over **3 years** with a 2% fee;
- its own wording.

These fall under **BV-04** (card treatment), **BV-17** (indicative options) and **BV-18 / LA-02** (wording). Approvers of those rows should review both calculators.

## 3. Formal approvals (13)

| ID | Approval | Decision required | Responsible approver | Evidence required | Status | Blocking impact | Depends on | Decision | Signed by | Sign-off date | Reference |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| LA-01 | Regulatory position | Is FynnEdge an LSP under the RBI Digital Lending Directions for these loans? What follows: KFS/APR by the lender, unbiased multi-lender display (OD9), direct payment to old lenders. | Legal / Compliance | `09-security-compliance.md` §3; partner DSA/LSP agreements | Pending | Blocks switch-on; may require changes to the portal's lender display | — | | | — | |
| LA-02 | Consent and disclaimer wording | Approve or replace all consent texts (assessment, contact incl. TRAI/DND, lender sharing, external analysis), the disclaimers and the warning wording (BV-18), in English and Hindi, under the DPDP Act and Rules | Legal / DPO + Compliance | Debt Consolidation Settings → Disclaimers and consent; `09-…` §3; BV-18 | Pending | Blocks switch-on | LA-01, LA-03 | | | — | |
| LA-03 | Fee wording | Does FynnEdge charge borrowers any fee? How is it disclosed (OD6)? | Legal + Owner | Debt Consolidation FAQs page; partner agreements | Pending | Blocks switch-on | LA-01 | | | — | |
| FA-01 | Financial assumptions | Approve: GST 18% (OD1); card minimum 5% (OD3); default tenure 60 months; rate-mismatch tolerance 1.5 points; NPA at 90 days past due; and BV-01–06 and BV-15 | Finance + Credit | Workbook BV rows, Checks A–B; HV-01 results | Pending | Blocks switch-on | HV-01 (evidence for BV-01, BV-06) | | | — | |
| FA-02 | Calculation methods | Approve loan sizing, the net-benefit definition, cost and cash break-even, and part-prepayment (BV-07, BV-09–11) | Finance + Head of Advisory | Workbook BV rows, Check C; HV-01 results | Pending | Blocks switch-on | HV-01 (evidence for BV-09) | | | — | |
| SC-01 | Suitability score | Approve weights 30/25/20/15/10, thresholds and bands (OD2), and the rule that "no net benefit = Not financially beneficial" (BV-14). If changed, a new scoring version is activated by a second person. | Head of Advisory + Compliance | Workbook BV-14, Check D; cases V01–V10 | Pending | Blocks switch-on | SC-02, FA-02 | | | — | |
| SC-02 | Affordability bands | Approve FOIR ≤40/≤50/≤65% and DSCR ≥1.75/≥1.4/≥1.2 (BV-12) | Credit | Workbook BV-12, Check D; partner lenders' norms | Pending | Blocks switch-on | — | | | — | |
| LP-01 | Lender policies | Enter each partner lender's debt consolidation policy from its own documents; a second person approves each one (OD4) | Credit (maker) + second approver | Lender documents; Debt Consolidation → Lender policies | Pending | Blocks switch-on (matching returns nothing without policies) | LP-02 (BV-16); LA-01 (lender agreements) | | | — | |
| LP-02 | Indicative options and route matrix | Approve or replace the standard options (OD7: PL 14%/60 m/2%, LAP 10.5%/120 m/1%, UBL 17%/48 m/2%, SBL 11.5%/84 m/1%), the savings calculator's defaults, and the route-to-loan-type matrix (BV-16, BV-17) | Credit | Settings; `config/debt_consolidation.php`; current partner rate cards | Pending | Blocks switch-on | — | | | — | |
| HV-01 | Historical case validation | Accept the parallel-run results: 10–20 past cases, differences above ₹1 explained (BV-13) | Head of Advisory + Credit | Completed `18-…` record sheets | Pending — **not started** | **Decisive**: blocks switch-on and the final sign-off of FA-01 and FA-02 | Legal/DPO confirmation that redacted past-case documents may be used (HV-00); source documents (`18-…` §3) | | | — | |
| OP-01 | Operational readiness | Set the team alert email; train advisors (`13-operational-user-guide.md`); decide retention of submitted cases (OD8); decide on DLT SMS templates; approve the quotation verification procedure (BV-08) | Operations + Owner | Settings; training record; written retention decision | Pending | Blocks switch-on (email, training, BV-08) | LA-02 (training uses final wording) | | | — | |
| CO-01 | Code review and commit | Review and commit the uncommitted branch, or authorise Claude to commit | Owner / tech lead | `git status`; this documentation set | Pending | Blocks deployment | PR-01 | | | — | |
| DP-01 | Deployment authorisation | Explicit go-ahead to deploy. **Starting the new container also runs the migrations.** | Owner | `12-deployment.md`; PR-02–PR-05 | **Awaiting owner** | Blocks deployment | CO-01, PR-01–PR-05 | | | — | |

## 4. Calculation and business validation items (18)

Technical evidence (column "Technical evidence — not approval") shows only that the engine matches its specification.

| ID | Item | Decision required | Approver | Evidence required for sign-off | Technical evidence — not approval | Parent | Status | Blocking impact | Signed by | Sign-off date |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| BV-01 | EMI and per-month rounding | Accept the EMI formula, half-up rounding to the paisa per month, and a final instalment that clears the balance | Credit / Finance | Check A recomputed; 3 real lender repayment schedules (total interest within ₹1, or the difference explained) | 827/827 match; Check A diff 0 | FA-01 | Pending | Blocks FA-01 | | — |
| BV-02 | Existing loan, EMI and months known | Accept the implied-rate check and the 1.5-point tolerance (flat-rate detection) | Credit | A real flat-rate NBFC statement compared with case V10 | V10 matches the reference | FA-01 | Pending | Blocks FA-01 | | — |
| BV-03 | Existing loan, partial details | Accept "EMI only / months only / neither" handling and the 60-month assumed tenure | Credit | Written confirmation that the assumption is acceptable for advice | Matches the reference | FA-01 | Pending | Blocks FA-01 | | — |
| BV-04 | Credit cards | Accept: a planned payment is paid until cleared; without one, like-for-like over each option's tenure; FOIR at 5% of the balance. **Also:** the savings calculator's default card payment (5% minimum due, held fixed). | Credit | 2 card statements (minimum due %); both calculators reviewed | V02 matches; calculator tests use reference figures | FA-01 | Pending | Blocks FA-01 | | — |
| BV-05 | Interest-only debts | Accept monthly interest on the full balance with the principal in the last month (overdraft, CC, gold bullet, invoice, working capital) | Credit | An overdraft statement and a gold-loan bullet schedule | V03/V04 match | FA-01 | Pending | Blocks FA-01 | | — |
| BV-06 | Foreclosure estimate | Accept the formula (principal + overdue + pending + accrued on full closure + charge + GST + other) and GST at 18% | Finance / Legal (GST) | Check B; 5 real foreclosure letters (via HV-01) | V07 matches; Check B diff 0 | FA-01 | Pending | Blocks FA-01 | | — |
| BV-07 | Part-prepayment | Accept: charge only on the amount paid down, no accrued interest, the loan keeps its EMI and finishes sooner (some lenders reduce the EMI instead) | Credit | Part-prepayment practice confirmed with 2 lenders | V05 matches | FA-02 | Pending | Blocks FA-02 | | — |
| BV-08 | Lender quotation | Approve the procedure for recording and verifying foreclosure quotations, and the validity rule | Operations | A written operating procedure; a trial run in the admin | Covered by workflow tests | OP-01 | Pending | Blocks OP-01 | | — |
| BV-09 | New loan amount and fee | Accept loan sizing: the smallest whole-rupee loan whose payout covers what must be paid; fee + GST deducted or paid upfront; shortfall on a lower sanction | Credit / Finance | Check C; 3 real sanction letters (fee, GST, net disbursal) via HV-01 | 827/827 match (after the D-23 correction); brute force over 480,000 amounts | FA-02 | Pending | Blocks FA-02 | | — |
| BV-10 | Net financial benefit | Agree the definition (total outflow from today, principal counted once on each side) as how FynnEdge presents savings | Head of Advisory | Check C | Breakdown identity exact in tests | FA-02 | Pending | Blocks FA-02, SC-01 | | — |
| BV-11 | Break-even | Agree cost break-even and cash break-even, and the staff label "Month 0" | Head of Advisory | Workbook definitions; staff screen reviewed | Matches the reference | FA-02 | Pending | Blocks FA-02 | | — |
| BV-12 | Affordability (FOIR / DSCR) | Accept the formulas and bands | Credit | Check D; partner lenders' FOIR/DSCR norms | Matches the reference | SC-02 | Pending | Blocks SC-02, SC-01 | | — |
| BV-13 | Historical case validation | Run the parallel run and accept its results | Head of Advisory + Credit | Completed record sheets per `18-…` | **Not started** | HV-01 | Pending | Blocks HV-01, FA-01, FA-02 | | — |
| BV-14 | Suitability score | Accept the weights, thresholds, rounding, bands and the not-beneficial override | Head of Advisory + Compliance | Check D; V01–V10 bands reviewed for reasonableness | Matches the reference | SC-01 | Pending | Blocks SC-01 | | — |
| BV-15 | Not consolidatable now | Accept exclusion of NPA, settled, written-off, disputed, ≥90 DPD and in-lock-in debts | Credit | Partner lenders' acceptance rules | V09 matches | FA-01 | Pending | Blocks FA-01 | | — |
| BV-16 | Route matrix | Accept which loan types each financing route can take over | Credit | Matrix reviewed against lender products | Config reviewed | LP-02 | Pending | Blocks LP-02, LP-01 | | — |
| BV-17 | Indicative options | Replace or confirm the standard options and the savings calculator's defaults (14% / 3 years / 2%) with current market ranges | Credit | Current partner rate cards | — | LP-02 | Pending | Blocks LP-02 | | — |
| BV-18 | Warnings never hidden by a lower EMI | Approve the wording of every warning shown to customers and staff (English and Hindi) | Compliance | Warning list (`06-financial-engine.md` §4); screenshots of both calculators | Warnings covered by tests | LA-02 | Pending | Blocks LA-02 | | — |

## 5. What can start now, and what must wait

**Can start immediately, in parallel:**
- **LA-01** (regulatory position). It is the root legal decision.
- **SC-02 / BV-12** (affordability bands).
- **LP-02 / BV-16, BV-17** (options and route matrix).
- **HV-01 / BV-13** (historical cases). It has the longest lead time.
  - Ask Legal/DPO **today** for HV-00: confirmation that redacted documents from past cases may be used for internal validation. This is a new use of personal data.
  - Then start collecting documents.
- **FA-01 items** that need only statements: BV-02, BV-03, BV-04, BV-05, BV-15.
- **BV-07, BV-10, BV-11** (method definitions).
- **OP-01 / BV-08** (quotation procedure) and the team alert email.
- **CO-01** (code review), after PR-01.

**Must wait for another decision:**

| Item | Waits for | Why |
| --- | --- | --- |
| LA-03 Fee wording | LA-01 | LSP rules govern who may charge the borrower |
| LA-02 Consent and disclaimer wording | LA-01, LA-03 | Notices must reflect the regulatory position and the fee decision |
| BV-18 Warning wording | Content: none. Final approval: alongside LA-02 | The customer-facing wording set is approved together |
| HV-01 document collection | HV-00 (Legal/DPO) | Re-using past customers' documents needs a lawful basis; redaction rules in `18-…` §4 |
| FA-01 final sign-off (BV-01, BV-06) | HV-01 | Needs real schedules and foreclosure letters |
| FA-02 final sign-off (BV-09) | HV-01 | Needs real sanction letters |
| SC-01 / BV-14 Suitability score | SC-02, FA-02 (BV-10) | The score uses the affordability bands and the net-benefit definition |
| LP-01 Lender policies | LP-02 (BV-16), LA-01 | Policies must fit the approved routes and the lender agreements |
| OP-01 training | LA-02 | Advisors are trained on the final wording |
| DP-01 Deployment | CO-01, PR-01–PR-05, **INF-02** (Docker PHP runtime, `27-…` §6) | See §6 |
| **Switch-on (go-live)** | **All 13 approvals** | The service stays off until every row is approved |

Deployment (DP-01) does not need the business approvals: the service ships switched off, with all pages in draft. **Switching the service on** needs every row in §3 and §4 approved.

## 6. Deployment prerequisites (technical and operational — not business approvals)

| ID | Prerequisite | Owner | Status |
| --- | --- | --- | --- |
| PR-01 | Re-run the full regression suite on the release candidate, compared with the clean baseline | Engineering | **Done** 26 Nov 2026: run 8, 3,286 tests, 3,270 passed; the 15 pre-existing Experian failures, also present on the clean baseline; 1 test updated for the Savings Calculator gate and re-run green (`20-…`) |
| PR-02 | Production mail transport confirmed | Operations | Open |
| PR-03 | `schedule:run` confirmed running in production (it is not in the Docker image) | Operations | Open |
| PR-04 | `storage/app/private` on persistent, backed-up storage | Operations | Open |
| PR-05 | Backup (database dump, private storage, `APP_KEY`) taken immediately before deployment | Operations | Open |

## 7. Change log

| Date | Change | By |
| --- | --- | --- |
| 26 Nov 2026 | Tracker created; 13 approvals and 18 BV items reconciled; all pending | Claude (documentation only) |
| 26 Nov 2026 | Release-candidate stage: PR-01 done; gate matrix `30-…` created; decision packs `21-…` to `25-…` added; DP-01 now also depends on INF-02. **No approval recorded; every business row still pending.** | Claude (documentation only) |
