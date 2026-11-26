# 19 — Business sign-off checklist: Debt Consolidation

**For:** each approving function. Each section lists only that function's items, what to review, and where to record the decision. IDs refer to `17-BUSINESS-APPROVAL-TRACKER.md`, where every decision must also be recorded.

**How to use**
- Tick each review step.
- Choose one decision per item:
  - **A**: Approved.
  - **AC**: Approved with changes. List the changes and who makes them.
  - **R**: Rejected. Give the reason.
  - **D**: Deferred. Give a new date.
- Sign the block at the end of your section.

**Rules**
- An item is approved only by its named approver.
- Passing tests, the 827 matching figures and these documents are evidence, **not approval**.
- If an approval changes a figure or wording, the change is made in the admin settings, or in a new scoring or formula version, and is then re-checked.

Workbook: `validation/DC-financial-validation-workbook.xlsx` ("Business validation" sheet and Checks A–D).

**Release-candidate stage (26 Nov 2026).** Every gate, including the technical and owner gates, is now listed in one matrix: `30-RELEASE-GATE-MASTER-REGISTER.md` (groups A–G). The decision packs for each function are:
- `21-…`: HV-00;
- `23-…`: BV items;
- `24-…`: Legal A–L, with DRAFT wording;
- `25-…`: SOPs;
- `26-…`: security;
- `27-…`: infrastructure;
- `lender-policy/`: LP-01.

---

## 1. Legal (with DPO)

| ID | Review | Done | Decision | Notes |
| --- | --- | --- | --- | --- |
| HV-00 | Decide the 9 points in `21-HV-00-DECISION-PACK.md`, including the retention option R1–R4. Record them on the workbook's *HV-00 gate* sheet (`validation/historical/`). | ☐ | A / AC / R / D | **Do first.** It unblocks the historical-case check. |
| LA-01 | Regulatory position under the RBI Digital Lending Directions (LSP or not), KFS/APR, multi-lender display (OD9), payment of old lenders (`09-security-compliance.md` §3); partner DSA/LSP agreements | ☐ | A / AC / R / D | Root decision; LA-02, LA-03 and LP-01 depend on it |
| LA-03 | Whether any borrower fee is charged, and the disclosure wording (OD6); with the Owner | ☐ | A / AC / R / D | After LA-01 |
| LA-02 | Every consent text (assessment, contact incl. TRAI/DND, per-lender sharing, external analysis), the notices under the DPDP Act and Rules, all disclaimers, English and Hindi (Debt Consolidation Settings → Disclaimers and consent) | ☐ | A / AC / R / D | After LA-01 and LA-03; approve together with BV-18 (Compliance) |
| BV-06 (GST part) | GST at 18% on foreclosure charges and processing fees | ☐ | A / AC / R / D | With Finance |
| LA-02 (J) | Privacy policy, terms and disclaimer sections for debt consolidation (`24-…` J, DRAFT wording) | ☐ | A / AC / R / D | With the DPO |
| M3 | Erasure scope for abandoned drafts and customer requests, including consent records (`26-…` §5) | ☐ | A / AC / R / D | With the DPO and OP-01 retention |
| L | Wording flagged on the website: "free", "true net savings", "partner banks", NOC help, "Lowest rate" (`24-…` L) | ☐ | A / AC / R / D | |

Signed (Legal): ______________ Name: ______________ Date: ________

## 2. Compliance

| ID | Review | Done | Decision | Notes |
| --- | --- | --- | --- | --- |
| BV-18 | Wording of every warning (higher total cost; lower EMI but higher cost; longer tenure; rate not lower; unsecured debt becoming secured; tax-benefit loss; shortfall; extra borrowing), on both calculators and in reports, English and Hindi | ☐ | A / AC / R / D | Part of LA-02 |
| SC-01 / BV-14 | Suitability score: weights 30/25/20/15/10, thresholds, bands, and the "Not financially beneficial" override. Confirm the score is never presented as an approval. | ☐ | A / AC / R / D | With the Head of Advisory, after SC-02 and BV-10 |
| LA-01 / LA-02 | Co-review with Legal | ☐ | A / AC / R / D | |

Signed (Compliance): ______________ Name: ______________ Date: ________

## 3. Finance

| ID | Review | Done | Decision | Notes |
| --- | --- | --- | --- | --- |
| BV-01 | EMI formula and paisa rounding: recompute Check A; compare with 3 real repayment schedules (from HV-01) | ☐ | A / AC / R / D | Final sign-off after HV-01 |
| BV-06 | Foreclosure formula and GST: recompute Check B; compare with 5 real letters (from HV-01) | ☐ | A / AC / R / D | Final sign-off after HV-01 |
| BV-09 | Loan sizing, fee and GST, net disbursal, shortfall: recompute Check C; compare with 3 sanction letters (from HV-01) | ☐ | A / AC / R / D | Final sign-off after HV-01 |
| FA-01 | Confirm the assumptions: GST 18% (OD1), card minimum 5% (OD3), default tenure 60 months, rate-mismatch tolerance 1.5 points, NPA at 90 DPD | ☐ | A / AC / R / D | With Credit |
| FA-02 | Confirm the methods (BV-07, BV-09, BV-10, BV-11) | ☐ | A / AC / R / D | With the Head of Advisory |

Signed (Finance): ______________ Name: ______________ Date: ________

## 4. Credit

| ID | Review | Done | Decision | Notes |
| --- | --- | --- | --- | --- |
| BV-02 | Flat-rate detection (1.5-point tolerance): case V10 against a real NBFC flat-rate statement | ☐ | A / AC / R / D | |
| BV-03 | Handling of loans with only EMI, only months, or neither; the assumed 60-month tenure | ☐ | A / AC / R / D | |
| BV-04 | Cards: planned payment vs like-for-like; 5% for FOIR; the savings calculator's default payment (5% minimum due, held fixed); 2 card statements | ☐ | A / AC / R / D | |
| BV-05 | Interest-only debts: an overdraft statement and a gold-loan bullet schedule | ☐ | A / AC / R / D | |
| BV-07 | Part-prepayment (EMI kept, finishes sooner): practice of 2 lenders | ☐ | A / AC / R / D | |
| BV-12 / SC-02 | FOIR ≤40/≤50/≤65% and DSCR ≥1.75/≥1.4/≥1.2 against partner lenders' norms | ☐ | A / AC / R / D | Can start now |
| BV-15 | Not consolidatable now: NPA, settled, written off, disputed, ≥90 DPD, lock-in | ☐ | A / AC / R / D | |
| BV-16 | Route matrix (`config/debt_consolidation.php`) | ☐ | A / AC / R / D | Before LP-01 |
| BV-17 / LP-02 | Standard options (OD7) and the savings calculator's defaults (14% / 3 years / 2%) against current partner rate cards | ☐ | A / AC / R / D | |
| LP-01 | Enter each partner lender's policy from its documents (maker); a second person approves each one in the admin | ☐ | Per policy | After BV-16 and LA-01 |
| HV-01 / BV-13 | Review the historical-case results (`18-…` §9) | ☐ | A / AC / R / D | With the Head of Advisory |

Signed (Credit): ______________ Name: ______________ Date: ________

## 5. Head of Advisory

| ID | Review | Done | Decision | Notes |
| --- | --- | --- | --- | --- |
| HV-01 / BV-13 | Run the historical-case check (`18-…`): owner, people, dates; approve the summary | ☐ | A / AC / R / D | **Decisive item** |
| BV-10 | Net financial benefit definition (total outflow from today, principal counted once on each side) as how FynnEdge presents savings | ☐ | A / AC / R / D | |
| BV-11 | Cost and cash break-even definitions; the staff label "Month 0" | ☐ | A / AC / R / D | |
| SC-01 / BV-14 | Suitability score (with Compliance); review the bands of cases V01–V10 for reasonableness | ☐ | A / AC / R / D | After SC-02 and BV-10 |
| FA-02 | Calculation methods (with Finance) | ☐ | A / AC / R / D | After HV-01 |
| — | Decide each D3 and D5 item raised by the historical-case check | ☐ | Per item | |

Signed (Head of Advisory): ______________ Name: ______________ Date: ________

## 6. Operations

| ID | Review | Done | Decision | Notes |
| --- | --- | --- | --- | --- |
| BV-08 | Written procedure for recording and verifying foreclosure quotations; trial in the admin on a non-production copy | ☐ | A / AC / R / D | |
| OP-01 | Team alert email set (Debt Consolidation Settings) | ☐ | Done / not done | |
| OP-01 | Advisors trained on `13-operational-user-guide.md` (training record kept) | ☐ | Done / not done | After LA-02 |
| OP-01 | Retention period for submitted cases (OD8), with the Owner and Legal | ☐ | A / AC / R / D | |
| OP-01 | DLT SMS templates: needed or not | ☐ | Decision | |
| SOPs | Approve SOP-01 to SOP-13 and the proposed time limits (`25-…`) | ☐ | A / AC / R / D | SOP-05 is BV-08 |
| OPS-D1 | Keep the second-person checks on quotations, closure payments and closure verification procedural, or have them enforced in the system (`25-…` §1) | ☐ | Decision | |
| PR-02–PR-05 | Production mail transport; scheduler running; persistent backed-up private storage; backup before deployment. Procedures are in `27-…` §5 and `31-…` §2. | ☐ | Confirmed / not confirmed | Prerequisites for deployment, not approvals |

Signed (Operations): ______________ Name: ______________ Date: ________

## 7. Owner

| ID | Decision | Done | Decision | Notes |
| --- | --- | --- | --- | --- |
| LA-03 | Fee to borrowers (with Legal) | ☐ | A / AC / R / D | |
| OP-01 | Retention (OD8), SMS | ☐ | A / AC / R / D | |
| CO-01 | Review and commit the branch, or authorise Claude to commit | ☐ | A / D | After PR-01 |
| INF-02 | The Docker runtime is PHP 8.3, but the dependencies need ≥ 8.4.1. Confirm how production is built; approve `release/dockerfile-php-runtime.patch` or record the real build (`27-…` §6). | ☐ | A / D | **Blocks deployment** |
| INF-01 | Explicit migration step: approve `release/entrypoint-explicit-migrations.patch`, or keep migrate-on-start with the runbook discipline (`27-…` §4.4) | ☐ | A / D | Before DP-01 |
| INF-04 / INF-05 | Restrict accepted hosts in nginx; encrypt sessions (site-wide production settings) | ☐ | A / D | |
| DP-01 | Authorise deployment by `31-DEPLOYMENT-RUNBOOK.md`. Unless INF-01 is applied, starting the new container **also runs the 8 migrations**; the service stays off. | ☐ | A / D | After CO-01, INF-02 and PR-01–PR-05 |
| GL-01 (go-live) | Switch the service on by `34-GO-LIVE-AND-ACTIVATION-CHECKLIST.md` | ☐ | A / D | **Only after every item in `17-…` §3 and §4, HV-00, and `34-…` G-01 to G-12 is done** |

Signed (Owner): ______________ Name: ______________ Date: ________
