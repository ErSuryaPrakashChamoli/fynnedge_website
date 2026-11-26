# 20 — Regression closure report (PR-01)

**For:** the Owner and the tech lead (CO-01, DP-01).
**Date:** 26 Nov 2026.
**Result:** **PR-01 COMPLETE.**
- The release candidate passes every test except the same 15 tests that fail on the clean pre-project code (the external Experian API, F-1).
- One test failed in run 8 because of this stage's own Savings Calculator gate (W-01). Its expectation was updated to the exact new behaviour, and it passes (F-3).
- No test was removed or weakened.
- **Run 9 (final, after the Owner decisions):** 3,294 tests, 3,279 passed. The 15 that fail are exactly the clean baseline's 15 Experian failures: nothing new, nothing missing (`evidence/run9-full-suite-after-owner-decisions.txt`).

## 1. How the runs were made

| Item | Detail |
| --- | --- |
| Test database | In-memory SQLite only (`phpunit.xml`). `tests/TestCase.php` refuses any other database. Every run used `APP_CONFIG_CACHE` / `APP_ROUTES_CACHE` / `APP_EVENTS_CACHE` pointed at non-existent files, so a cached config can never point tests at MySQL. |
| Clean baseline | Commit `084825b` (= `origin/main`, the last commit before the project), checked out as a separate worktree with its own `vendor` and the same `.env`, run with the same command |
| Runtime | PHP 8.5.4, Pest, Laravel 13.29, local machine |
| Times | Baseline and run 5: 13:44–14:09 IST. Run 8: 15:51–16:07 IST. All outside the 00:00–05:30 IST window in which one known rates test fails (§4). |

| Run | Code | Tests | Passed | Failed | Errors | Assertions | Duration |
| --- | --- | ---: | ---: | ---: | ---: | ---: | --- |
| Baseline | `084825b` | 3,011 | 2,996 | 14 | 1 | 12,712 | 18 m 56 s |
| 5 | Branch before this stage's fixes | 3,270 | 3,255 | 14 | 1 | 13,676 | 25 m 14 s |
| 6 | — | stopped before completion: a migration defect was found and the code was about to change (§3) | | | | | |
| 7 | — | stopped before completion: an independent review found more defects and the code changed during the run, so its result would not describe one version | | | | | |
| 8 (first attempt) | — | lost: the machine restarted at 15:43 IST during the run; re-run from the start | | | | | |
| **8** | **Release candidate (final)** | **3,286** | **3,270** | **15** | **1** | **13,718** | **15 m 38 s** |
| 8 follow-up | `DebtConsolidationSitePreferenceTest` after updating one expectation (F-3) | 12 | 12 | 0 | 0 | 70 | 3 s |
| **9** | **After the Owner decisions (INF-01/02/04/05, W-03, OPS-D1, M3)** | **3,294** | **3,279** | **14** | **1** | **13,786** | **19 m 57 s** |

**Raw outputs.**
- Run 8's full output is kept in the repository at `evidence/run8-full-suite-release-candidate.txt`.
- The baseline was re-run after the restart, at 16:27–16:38 IST, from a fresh checkout of `084825b`: `evidence/baseline-084825b-full-suite.txt`. It gave the same result as the first baseline run: 3,011 tests, 2,996 passed, the same 15 Experian failures.
- Comparing that output with run 8, the only failing test not on the baseline is F-3; no baseline failure is missing from run 8.
- Run 5's raw output was in the session's temporary folder, which a machine restart at 15:43 IST wiped. Its figures were recorded here when it finished.
- The F-1 classification does not depend on those files. It rests on the repository itself (`git show 084825b:routes/external.php` has no Experian external route), which anyone can re-check.

## 2. Suites covered (mandate §3)

All suites run in the full run. The table maps each requested category to where it is tested.

| Category | Where (test counts on the final code) | Result in run 8 |
| --- | --- | --- |
| Complete Laravel suite | All of `tests/` (3,286) | See §1 |
| DC unit tests | `tests/Unit/DebtConsolidation` (64: amortisation, projector, foreclosure, comparator, affordability, suitability, portfolio, engine) | Pass |
| DC feature tests | `tests/Feature/DebtConsolidation` (172) and the Savings Calculator (18) | Pass |
| Database migration tests | `DebtConsolidationMigrationTest`; rename, rollback and settings migration tests in `DebtConsolidationPagesTest` (3). Every test also migrates a fresh database. Plus the upgrade rehearsal from the baseline (`27-…` §4.3). | Pass |
| Financial calculation tests | DC unit tests; `AssessDebtApplicationTest` (6); Savings Calculator reference-figure tests; engine re-run of V01–V10 identical to the 827/827 evidence (`23-…`) | Pass |
| API tests | `DebtConsolidationApiTest` (6) and the other modules' API tests | DC pass; the 15 Experian failures are pre-existing (§4) |
| Permission and role tests | `DebtConsolidationAdminTest` (roles, acceptance fixes, security review fixes), Filament admin navigation and Shield tests | Pass |
| Customer application workflow | `DebtConsolidationApplicationFormTest` (13), `DebtConsolidationEnquiryFormTest` (10), `RecordDebtConsolidationEnquiryTest` (2) | Pass |
| Admin workflow | `DebtConsolidationAdminTest` (41), `DebtCaseWorkflowTest` (31) | Pass |
| Customer portal | `DebtConsolidationPortalTest` (12) | Pass |
| Document upload and verification | Admin upload, tamper and download tests; portal upload tests; workflow document tests | Pass |
| Lender matching | Workflow matching tests; admin policy create → second-person approval → match | Pass |
| Report generation | Workflow and portal report tests (8 report types) | Pass |
| Calculators | `DebtConsolidationCalculatorTest` (15), Savings Calculator (18), every site calculator test | Pass |
| Security | Security-review tests (H1, M1, M2, L1, L2, L3), portal isolation, OTP limits, API authorisation; site security-header tests | Pass |
| Queue and scheduler | Queued-notification encryption test; notification dispatch tests; `DebtConsolidationRetentionTest` (2). The retention job is registered (`schedule:list`: `0 3 * * *`). | Pass |
| Frontend and responsive | `SiteStylesheetTest`, `SiteBrandingTest`, `DebtConsolidationSitePreferenceTest`, `DebtConsolidationHindiTest`; plus the headless browser check at 3 widths (`28-…`) | Pass |

**Post-acceptance changes covered:**
- the Savings Calculator;
- the date-of-birth field;
- the calculator layout changes;
- the test-safety guard;
- the parallel Analyser and header changes;
- every fix from this stage.

All are inside the runs above.

## 3. Defects found during this stage

Each defect got a test that was run on the unfixed code (failed) and on the fixed code (passed): H1, M1, M2, L1, L2, L3, W-01, W-02, W-04, W-05, W-06, W-07. See `29-…` §3, `26-…` and `28-…`.

**No financial formula was changed.**

## 4. Every failure, classified

The mandate asks five questions for each failure:
- Is it on the clean baseline?
- Is it on the branch?
- Is it caused by this project?
- Does it block release?
- What is the evidence?

| # | Test(s) | On clean baseline? | On branch (run 8)? | Caused by this project? | Blocks release? | Evidence |
| --- | --- | --- | --- | --- | --- | --- |
| F-1 | `ExternalExperianApiTest`: 14 failures and 1 error (401/403/422/202/200 expected, 404 received; one "No query results for model Customer") | **Yes**: the same 15, in the run of 26 Nov 13:44 | **Yes**: the same 15 | **No.** The tests call `/api/v1/external/experian/*`. No such route is registered in `routes/external.php`, either at `084825b` or on the branch. The branch adds only the DC route group to that file. It does not touch `app/Modules/Experian`, `ExperianController`, `routes/experian.php` or the Experian tests (`git diff 084825b` on those paths is empty). | **No** for Debt Consolidation. It is a pre-existing defect in the Experian external API, reported to the Owner (`35-…` P-01). | Run outputs; `git diff 084825b -- routes/external.php`; `git log -S "external/experian"` |
| F-2 | `PublicRateDisplayTest` (1 test), between 00:00 and 05:30 IST only | Yes: reproduced on a clean `084825b` checkout at acceptance | Not in run 8 (the run was outside the window) | **No.** The rate "As listed on …" label uses the UTC date. | No | Acceptance evidence (`16-…`); passes in the baseline, run 5 and run 8 |

| F-3 | `DebtConsolidationSitePreferenceTest` › *leaves the desktop menu as it is without the service while the service is live* (1 test) | No (the test does not exist there) | Yes, in run 8; **passes after the update** | **Yes. A deliberate behaviour change by this stage (W-01).** The test compared the whole desktop menu with the service off and on. Since W-01 the Savings Calculator appears in the Calculators dropdown only while the service is live, so the menus now differ by exactly that link. The test now asserts: the link is absent when off and present when on; everything else is identical; there is still no DC item in the header row (the rule in `.ai/rules/site.md`). Nothing was loosened: any other difference still fails. | No | Run 8 failure diff (the only added lines are that `<a>`); follow-up run 12/12 (`evidence/run8-followup-site-preference-test.txt`) |

**No other failure occurred.** None is classified as pre-existing without the evidence above.

## 5. Test inventory: nothing removed or weakened

- Every test name was listed on the baseline (3,011) and on the final branch (3,286) and compared, after normalising the random IDs inside some dataset labels.
  - **Removed: 0.**
  - **Added: 275**: DC unit and feature tests, the Savings Calculator, admin navigation entries for the DC screens, the header and stylesheet tests, the DC OTP purpose case, and this stage's fixes.
- Assertion lines removed from pre-existing test files:
  - **0** in `MobileOtpApiTest`, `SiteStylesheetTest` and `TestCase`;
  - **2** in `SiteBrandingTest`. Both belong to the Owner-requested removal of the desktop "Eligibility" menu link (26 Nov 2026). They are replaced by *leaves eligibility to the Check Eligibility button, with no Eligibility menu link*, which asserts the new behaviour. This is not a weakening.
- No test is skipped (`->skip()`, `markTestSkipped`) in the DC suites, and none was added anywhere.

## 6. Release impact

| Item | Impact |
| --- | --- |
| DC release | No failing test is caused by DC. PR-01 is satisfied. |
| Experian external API (F-1) | Broken before this project and still broken. It does not block the DC release, but the Owner should know that the LMS cannot call the Experian external API. |
| Rates label timezone (F-2) | Cosmetic, overnight only. Not a release blocker. |
