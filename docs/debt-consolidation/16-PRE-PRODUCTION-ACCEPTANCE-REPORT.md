# 16 — Pre-production acceptance report: Debt Consolidation

**Date:** 26 Nov 2026 · **Branch:** `feature/nov_24_redis_check` (uncommitted) · **Environment:** local only · **Prepared for:** FynnEdge management, and the approvers named in §6 (Legal, Compliance, Credit, Finance, Head of Advisory).

Production has not been touched: nothing was deployed, migrated or switched on, and nothing was committed. The local database is in its original state (service off, all seven pages in draft, no debt consolidation cases, no pending migrations).

## 1. Result

**Classification: 2 — Technically ready, with business approvals pending.**

- **Technically:**
  - Every functional area was verified against its code and tests.
  - The acceptance review found 1 blocker, 6 major and 17 minor defects. All 24 were fixed, and each fix has an automated test.
  - The final full test run shows no regressions (§4).
  - The financial engine agrees with an independent calculation on all 827 compared figures, to the paisa (§3).
- **It is not live-ready.** Real applications must not be accepted until the approvals in §6 are given, the historical-case check (BV-13) is done, and you authorise deployment. Automated tests passing is not the same as business approval.

| | Technical completion (done) | Business approval (pending) |
| --- | --- | --- |
| Who decides | Engineering (verified here) | Legal, Compliance, Credit, Finance, Head of Advisory, Owner |
| Evidence | Code, 3,243 automated tests, validation workbook, review findings | Sign-offs in §6, historical-case results (BV-13) |
| Status | Accepted | **0 of 13 approvals given** |

## 2. How acceptance was done

1. **Documentation review.** Read `00`, `08`, `12` and `15` against the code. Mismatches were corrected (§8).
2. **Two independent code reviews**, given the code rather than the conclusions: one of the customer-facing paths, one of the staff-side workflow and security. Each finding was re-checked in the code before it was accepted or fixed. Most were also reproduced with a failing test first.
3. **Financial validation.**
   - 10 representative cases (18 options) were run through the production engine and through a separately written Python reference that follows the published specification.
   - The results are in a workbook with hand-check sheets using live Excel formulas (§3).
4. **Baseline proof.** The pre-existing failures were re-run on a clean checkout of the last commit, and the test inventory before and after was compared (§4).
5. **Deployment inspection.**
   - Read the Dockerfile, the entrypoint and the supervisor set-up.
   - Checked that the routes and every view compile for caching.
   - Confirmed the rollback order (§7).

## 3. Financial validation status

**Workbook:** `docs/debt-consolidation/validation/DC-financial-validation-workbook.xlsx`

- **Sheets:**
  - Business validation register.
  - Results summary.
  - Case inputs.
  - All 827 compared figures.
  - Hand checks A–D (amortisation schedule, foreclosure amount, new loan, fee, GST and savings, FOIR and suitability score).
- **Formulas:** 1,960; LibreOffice recalculation found no errors.
- **Reproducible:** run `engine-harness.php`, then `reference.py`, then `compare.py` in the same folder.

| What was checked | Result |
| --- | --- |
| Engine vs independent reference (money to the paisa, percentages to 0.01, months exact) | **827 / 827 agree** |
| Hand checks A–D (Excel formulas vs engine) | All differences 0. The FOIR display rounding differs by 0.004 % (the engine shows 29.28 %) |
| Defect found by the validation | **D-23.** When the processing fee is deducted from the payout, the engine sized the loan by rounding the formula to the paisa before rounding up. In about 0.15 % of amounts this left the payout a paisa short and raised a false "loan shortfall" warning (totals were still correct). The loan is now the smallest whole rupee whose actual payout covers the amount needed. A brute-force check of 480,000 amounts found no shortfall and no over-borrowing. |

**The cases.** All figures are hypothetical customer data. Loan terms are the shipped indicative options, which are themselves awaiting approval (BV-17). Foreclosure percentages are test inputs, not any lender's policy, and no lender policy was invented.

| Case | What it covers | Option | Net benefit | Monthly outgo | Engine verdict (score) | Warnings shown |
| --- | --- | --- | ---: | ---: | --- | --- |
| V01 | 3 personal loans (EMI+months, months only, EMI only) | PL 14% / 60 m / 2% | −₹1,56,445 | ₹10,846 less | Not financially beneficial (37.05) | lower EMI but higher cost; tenure extended |
| V01 | | PL 14% / 36 m / 2% | −₹50,334 | ₹3,860 less | Not financially beneficial (43.71) | same |
| V02 | 3 credit cards (one with no payment) + a card EMI | PL 14% / 60 m / 2% | ₹27,402 | ₹13,506 less | Potentially suitable (85.45) | repayment plan assumed (card) |
| V02 | | PL 15% / 36 m / 1.5% fee upfront | ₹44,991 | ₹10,884 less | Potentially suitable (97.69) | same |
| V03 | Business: term, NBFC, overdraft; machinery loan kept | UBL 17% / 48 m / 2% | −₹5,25,979 | ₹11,465 less | Not financially beneficial (33.70) | lower EMI but higher cost |
| V03 | | SBL 11.5% / 84 m / 1% | −₹6,68,025 | ₹40,070 less | Not financially beneficial (45.51) | + unsecured debt becomes secured |
| V04 | Secured + unsecured debts | LAP 10.5% / 120 m / 1% | −₹4,85,126 | ₹22,059 less | Not financially beneficial (47.30) | + unsecured debt becomes secured |
| V04 | | PL 14% / 60 m / 2% | −₹2,47,288 | ₹10,463 less | Not financially beneficial (39.57) | lower EMI but higher cost |
| V05 | Partial pay-down + card cleared + home loan kept | PL 14% / 60 m / 2% | ₹43,280 | ₹434 less | Borderline — advisor review (69.10) | tenure extended |
| V06 | Same debts, three tenures | 13.5% / 24 m | ₹34,203 | ₹1,425 **more** | Potentially suitable (80.05) | — |
| V06 | | 13.5% / 48 m | −₹29,761 | ₹7,229 less | Not financially beneficial (56.74) | costs recovered by month 9 but not sustained |
| V06 | | 13.5% / 84 m | −₹1,34,845 | ₹10,828 less | Not financially beneficial (50.07) | same |
| V07 | Overdue dues, charges, GST, verified quotation | fees and closure costs financed | −₹1,41,765 | ₹10,872 less | Not financially beneficial (39.28) | overdue liabilities |
| V07 | | fees and closure costs paid upfront | −₹1,26,390 | ₹12,125 less | Not financially beneficial (39.28) | |
| V07 | | lender sanctions only ₹6,00,000 | −₹1,07,498 | ₹13,569 less | Not financially beneficial (39.28) | + loan shortfall paid upfront |
| V08 | Lower EMI, higher total cost | PL 13% / 60 m / 2% | −₹1,70,464 | ₹17,737 less | Not financially beneficial (28.50) | lower EMI but higher cost |
| V09 | No rate gain, FOIR 81%, an NPA loan and a lock-in | PL 14% / 60 m / 2% | −₹1,72,820 | ₹7,253 less | Not financially beneficial (9.43) | NPA and lock-in loans kept out and flagged; FOIR stays "stretched" |
| V10 | Flat-rate loan (10% flat), weak business cash flow | UBL 17% / 48 m / 2% | −₹1,29,845 | ₹15,439 less | Not financially beneficial (41.39) | rate mismatch (implied rate used); low DSCR |

**What the validation proves.** The engine never presents a lower EMI as a saving: 14 of the 18 options lower the monthly payment yet cost more in total, and every one is marked "Not financially beneficial", whatever its score.

**What it does not prove.** It does not show that the formulas match how your partner lenders actually calculate. **The calculations needing manual business validation** are BV-01 to BV-18 in the workbook's "Business validation" sheet:

- EMI and per-month rounding.
- Projection of existing loans when only some details are known; flat-rate detection (1.5-point tolerance).
- Credit cards: like-for-like projection and the 5% minimum.
- Interest-only debts.
- The foreclosure formula (charge %, fixed fee, GST, accrued interest on full closure only).
- Part-prepayment (EMI kept, so the loan finishes sooner).
- Use of quotations.
- Loan sizing with fees deducted.
- The definition of net benefit.
- Cost and cash break-even.
- FOIR and DSCR formulas and bands.
- Suitability weights, thresholds and bands.
- Which loans cannot be consolidated now.
- The route matrix.
- The indicative options.
- Warning wording.

**Status:** technically validated; **business validation pending.** BV-13, the historical-case check, is the decisive one.

## 4. Regression status

| Check | Evidence | Result |
| --- | --- | --- |
| The 15 Experian failures pre-date this project | `ExternalExperianApiTest.php` was committed in `d446274` (before this project). At the last commit (`084825b`), `routes/external.php` has CIBIL routes only. Re-running the file on a clean checkout of `084825b`: 16 tests, 1 pass, 14 fail, 1 error (saved run output). The same 15 fail today. | **Pre-existing; not caused by this project** |
| No existing test removed | `pest --list-tests` at `084825b`: 3,011 tests, all still present. The 5 names that differ are dataset rows with random IDs; per-test row counts are identical. | Confirmed |
| No existing test weakened | Of the files that existed before this project, only `tests/Feature/MobileOtp/MobileOtpApiTest.php` changed: **+1 line** (a dataset row refusing the new OTP purpose), no removals. | Confirmed |
| Debt Consolidation suites | Unit: 64 tests (206 assertions). Feature: 151 tests (606 assertions). 31 of these were added for the acceptance fixes (2 unit, 29 feature). | **All pass** |
| Full suite (final, after all fixes; 25–26 Nov, 15 min 44 s) | 3,243 tests: 3,227 pass, 16 fail. **15** are the Experian failures above. **1** is `PublicRateDisplayTest` "labels a third-party rate…", which crossed midnight IST mid-run. That test expects today's date in India time, but the rate label prints the UTC date, so it fails every night between 00:00 and 05:30 IST. It fails identically on the clean checkout of `084825b` at the same time; earlier runs before midnight passed it. The rates module was not changed by this project. | **No regressions. All 16 failures pre-existing.** |
| Changes to this project's own tests during acceptance | Three fixtures were corrected to match the intended rules, with no assertion removed: <br>• two calculator tests now publish the assessment page, which the hand-off requires; <br>• the closure test now uploads a real NOC and puts the case in "Closure in progress", where closures exist. | Disclosed |

## 5. Functional and security acceptance

The acceptance review found 24 defects; all are now fixed, each with a test. The table shows where each area stands.

| Area | Status | Main evidence |
| --- | --- | --- |
| Public pages (7), CMS, SEO, sitemap, preview | Accepted | `DebtConsolidationPagesTest` |
| Website preference (strip, menu, mobile, footer, homepage) | Accepted after D-24 | `DebtConsolidationSitePreferenceTest` (10 tests) |
| Hindi / English | Accepted after D-21 | `DebtConsolidationHindiTest` and the Hindi preference test; every `__()` string has a Hindi entry |
| Calculators | Accepted after D-16, D-17 | `DebtConsolidationCalculatorTest` and the engine unit tests |
| Personal and business journeys; multiple liabilities; save and resume | Accepted after D-07, D-15, D-18, D-19 | `DebtConsolidationApplicationFormTest`, `DebtConsolidationEnquiryFormTest` |
| Financial assessment, suitability, foreclosure, savings | Accepted after D-23 | Engine unit tests and §3 |
| PDF reports (8) | Accepted | `DebtCaseWorkflowTest` (reports) |
| Enquiry and application management (admin) | Accepted after D-10 | `DebtConsolidationAdminTest` |
| Lender matching and lender applications | Accepted after D-05 | `DebtCaseWorkflowTest` |
| Application tracking and debt closure (NOC) | Accepted after D-03, D-08, D-09 | `DebtCaseWorkflowTest` |
| Customer portal | Accepted after D-22 | `DebtConsolidationPortalTest` |
| Admin permissions, maker-checker | Accepted after D-01, D-02, D-04 | `DebtConsolidationAdminTest` "acceptance fixes" |
| Notifications | Accepted after D-06, D-07, D-20 | Notification fakes in the enquiry, form and workflow tests |
| API (FynnAI-ready) | Accepted after D-11 | `DebtConsolidationApiTest` |
| Service on/off controls | Accepted after D-12 | Enquiry, form and calculator tests |
| Data integrity: snapshots, history, rollback | Accepted after D-13, D-14 | `AssessDebtApplicationTest`, `DebtConsolidationMigrationTest` |

### Defects found in acceptance and how they were resolved

| ID | Severity | Defect | Fix |
| --- | --- | --- | --- |
| D-01 | **Blocker** | Saving a role in Administration → Roles silently removed Debt permissions (15 from "Debt Advisor"). Approve / Activate could not be granted, so in practice only super admin could approve, and maker-checker did not apply. | Permissions registered with the role editor (approve/activate verbs; Custom permissions tab for documents, notes, collateral and consents) |
| D-02 | Major | Maker-checker bypass: a person could edit someone else's draft and then approve it; an edit page opened before approval could still change an approved policy | The "maker" is now the author or anyone who changed the draft (audit trail); approved versions are locked at model level; edit pages re-check on save |
| D-03 | Major | A closure payment of ₹1 could be "confirmed" when the quotation was recorded after the closure opened; a part-payment would have been held to the full quotation | Payment is confirmed only against a known amount (final amount, or the loan's quotation for a full closure, whenever it was recorded) |
| D-04 | Major | Anyone who could view a closure could confirm its payment | Needs Update on Debt Closure |
| D-05 | Major | After a customer withdrew lender consent, staff could not record the lender's decision, and the team was not told | Consent governs sharing only; lender outcomes are always recordable; staff are alerted on withdrawal |
| D-06 | Major | Customer case emails were never sent (the address is stored on the case, not the account) | Emails go to the address given on the case |
| D-07 | Major | A customer's update after "Information pending" was invisible to the advisor | Case returns to "Assessment in progress" and the advisor is alerted |
| D-08 | Minor | Auto-completion could turn a Withdrawn or Not proceeding case into Completed | Completes only from "Closure in progress" |
| D-09 | Minor | Any verified document, even another loan's, satisfied "NOC" | Must be an NOC for that loan, used by no other closure |
| D-10 | Minor | Case-page actions checked only "update case"; notes "Mark done" was unchecked; the timeline showed notes to everyone | Each action needs its own permission |
| D-11 | Minor | The API told keys without permission which references exist | Permission checked first |
| D-12 | Minor | Switching the service off did not stop forms already open (or the calculator hand-off from a preview) | All intake refused with a message |
| D-13 | Minor | Snapshot immutability covered only 5 columns; snapshots and status history could be deleted | Any change refused; only anonymous calculator snapshots are deletable (retention) |
| D-14 | Minor | Rolling back the seed migration could cascade-delete other modules' rows that reference the new document types | Rollback removes only unused types |
| D-15 | Minor | Saving on a case locked in another tab gave an error page (500) | Message shown instead |
| D-16 | Minor | Assessment links led to a 404 while the assessment page was in draft | Links appear only for reachable pages |
| D-17 | Minor | Calculator hand-off dropped the entered income, and wasn't linked when a case was resumed | Fixed |
| D-18 | Minor | A business loss was stored as zero | Stored as declared |
| D-19 | Minor | Crafted requests could submit a case without income or profile details, and an enquiry with altered consent, email or type (one caused a 500) | Server-side re-checks |
| D-20 | Minor | Customer not told when the case completed | Notified |
| D-21 | Minor | Option labels and the preview banner not translated into Hindi | Translated |
| D-22 | Minor | Portal accepted any document type, and uploads or consent on closed cases | Restricted |
| D-23 | Minor (financial) | A paisa of false "shortfall" in about 0.15 % of fee-deducted loans | Loan-sizing rule corrected (§3) |
| D-24 | Minor | Desktop menu spacing at ≥1280 px changed even with the service off | Original spacing kept while off |

**Security status.**

- **Controls in `09-security-compliance.md` verified and corrected:** permissions, maker-checker, customer isolation, encrypted documents and reports, consent records, insert-only history, immutable snapshots, API authentication, consent and throttling.
- **Residual items, by design:**
  - Super admin is exempt from maker-checker.
  - The RBI digital-lending and DPDP questions are legal decisions (LA-01, LA-02).

## 6. Business approval register

None of these can be given by engineering. All are **pending**. The workbook's "Business validation" sheet holds the detailed calculation sign-offs (BV-01–BV-18).

| # | Approval | What is approved | Approver | Evidence / where | Blocks go-live? | Status |
| --- | --- | --- | --- | --- | --- | --- |
| LA-01 | Legal: regulatory position | Whether FynnEdge acts as an LSP under the RBI Digital Lending Directions for these loans, and what follows (KFS/APR by the lender, multi-lender display, direct payment to old lenders) — OD5, OD9 | Legal / Compliance | `09-security-compliance.md` §3 | Yes | Pending |
| LA-02 | Consent and disclaimer wording | Wording, language versions and notice content for assessment, contact (TRAI/DND), lender-sharing and external-analysis consents and all disclaimers, under the DPDP Act and Rules | Legal / DPO | Debt Consolidation Settings | Yes | Pending |
| LA-03 | Fee wording | Whether any fee is charged to borrowers and how it is disclosed (OD6) | Legal + Owner | Debt Consolidation FAQs page | Yes | Pending |
| FA-01 | Financial assumptions | GST 18% (OD1); card minimum 5% (OD3); default tenure 60 months; rate-mismatch tolerance 1.5 points; NPA at 90 days past due | Finance + Credit | Workbook BV-01–BV-07, BV-15 | Yes | Pending |
| FA-02 | Calculation methods | Loan sizing, net-benefit definition, cost and cash break-even, part-prepayment treatment | Finance + Head of Advisory | Workbook BV-09–BV-11, Checks A–C | Yes | Pending |
| SC-01 | Suitability score | Weights 30/25/20/15/10, thresholds and bands (OD2); the rule that no net benefit means "Not financially beneficial" | Head of Advisory + Compliance | Workbook BV-14, Check D; Scoring configuration (activate a new version if changed) | Yes | Pending |
| SC-02 | Affordability bands | FOIR ≤40/50/65 %; DSCR ≥1.75/1.4/1.2 | Credit | Workbook BV-12 | Yes | Pending |
| LP-01 | Lender policies | Enter each partner lender's debt consolidation policy from its own documents, and have each approved by a second person (OD4). None are seeded. | Credit (maker) + second approver | Debt Consolidation → Lender policies | Yes (matching returns nothing without them) | Pending |
| LP-02 | Indicative options and route matrix | The standard comparison options (OD7) and which loan types each route can take over | Credit | Settings; `config/debt_consolidation.php` (BV-16, BV-17) | Yes | Pending |
| HV-01 | Historical case validation | Parallel run of 10–20 anonymised past cases against real foreclosure letters, sanction letters and schedules; differences above ₹1 explained | Head of Advisory + Credit | Workbook BV-13 | **Yes — decisive** | Pending |
| OP-01 | Operational readiness | Team alert email; advisors trained on `13-operational-user-guide.md`; retention of submitted cases (OD8); DLT SMS templates if SMS wanted | Operations + Owner | Settings; `13-…` | Yes (email and training) | Pending |
| CO-01 | Code review and commit | Review and commit the uncommitted branch (or authorise Claude to commit) | Owner / tech lead | `git status` | Yes | Pending |
| DP-01 | Deployment authorisation | Explicit go-ahead to deploy, which **also migrates** (§7) | Owner | `12-deployment.md` | Yes | **Awaiting your authorisation** |

## 7. Deployment readiness

| Item | Status | Notes |
| --- | --- | --- |
| Database migrations | Ready | 7 additive migrations; verified migrate → rollback → re-migrate on local MySQL. **The production entrypoint runs `migrate --force` on every container start, so deploying is migrating.** |
| Rollback | Ready | Instant: switch the service off. Full: export case data, then `migrate:rollback --step=7` **while the new image is still running**, then deploy the old image. The seed rollback no longer deletes shared document types (D-14). |
| Configuration and environment | Ready | No new variables are required. Two optional retention variables. Service settings live in the database and start **off**. |
| Mail transport | **Confirm** | Customer and staff emails need production mail configured (local uses `log`) |
| Storage and permissions | Ready / **confirm persistence** | Files are written by `www-data` to `storage/app/private/debt-consolidation/`; the entrypoint sets ownership. The directory must be on a persistent, backed-up volume. |
| Queue | Ready | Notifications are queued after commit on the existing `database` queue; supervisor runs `queue:work`. Restart workers on deploy. |
| Cache | Ready | `database` store supports the locks used to prevent duplicate cases |
| Scheduler | **Confirm** | `schedule:run` is not in the image; it must already run elsewhere for the site's existing jobs. Debt Consolidation adds a daily 03:00 retention job. |
| Backups | **To do before deploy** | Database dump, `storage/app/private`, and `APP_KEY` stored separately |
| Asset compilation | Ready | The Docker build runs `npm ci && npm run build` (verified locally) |
| Route, config and view caches | Ready | Run by the entrypoint. `route:cache` and `view:cache` verified on this code. |
| Production smoke test | Ready | Plan in `12-deployment.md` §3 (service off first, then one controlled test enquiry after switch-on) |
| Feature activation controls | Ready | Service on/off, per applicant type, per page, homepage wording; switching off withdraws all public entry points and refuses intake (D-12) |
| Staging rehearsal | Recommended | Not available here; migrating a copy of production data first is the safest proof |

## 8. Known limitations

- Figures come from what customers and advisors enter. Nothing is read from documents, because OCR and AI were excluded by design.
- The credit score used in matching is self-declared and labelled as such.
- PDFs are English only (dompdf cannot shape Devanagari).
- A signed-in customer with an open draft continues it. To start a request of the other applicant type they finish or withdraw it first, or an advisor opens it.
- Reports print the case's status and verification counts as they were when the PDF was generated, next to the stored figures. Each stored PDF is fixed, but a later regeneration reflects later status.
- A loan added after disbursement is not part of that financing and gets no closure tracker. Staff mark it "Exclude".
- Super admin is exempt from maker-checker (documented decision).
- SMS case updates are not sent (no DLT templates); email and portal updates work.
- Pre-existing and unrelated:
  - The 15 `ExternalExperianApiTest` failures (missing `/api/v1/external/experian/*` routes).
  - `PublicRateDisplayTest` fails between 00:00 and 05:30 IST: the rates module prints the UTC date on a third-party rate's "As listed on …" label, so the live label can show the previous day in that window. This is outside this project's scope and was reported, not changed.

Documentation corrected during acceptance: `04` (notifications, the 20-loan cap, switch-off behaviour), `05`, `06` (loan sizing and change log), `09`, `11` (role editor, per-action permissions), `12` (rewritten from the real Docker deployment), `13`, `14`. The README states that the desktop "Eligibility" link gives way while the service is live.

## 9. Exact remaining actions

| # | Action | Owner | Before |
| --- | --- | --- | --- |
| 1 | Review this report and the workbook | Owner | Everything below |
| 2 | Legal and compliance decisions LA-01–LA-03; edit the wording in Debt Consolidation Settings | Legal / Compliance | Switch-on |
| 3 | Finance and Credit sign-off of FA-01, FA-02, SC-01, SC-02, LP-02 (workbook) | Finance, Credit, Head of Advisory | Switch-on |
| 4 | Historical-case parallel run (HV-01 / BV-13); attach results to the workbook | Head of Advisory + Credit | Switch-on |
| 5 | Review and commit the branch (CO-01) | Owner / tech lead | Deployment |
| 6 | Confirm the mail transport, the scheduler and persistent storage; take the backup | Operations | Deployment |
| 7 | **Authorise deployment (DP-01)** | Owner | Deployment |
| 8 | Deploy, then run the smoke test with the service off (`12-deployment.md` §3) | Engineering | Switch-on |
| 9 | Enter and approve lender policies (LP-01); set the team email; train advisors (OP-01) | Credit, Operations | Switch-on |
| 10 | Publish the pages and FAQs; optionally word the homepage feature; switch the service on; run the controlled test enquiry | Marketing + Owner | Accepting real applications |

**I have stopped here** and am waiting for explicit deployment authorisation. Nothing in production will be changed without it.
