# 29 — Final production readiness report: Debt Consolidation release candidate

**For:** the Owner, and every approver named in `30-RELEASE-GATE-MASTER-REGISTER.md`.
**Date:** 26 Nov 2026.
**Branch:** `feature/nov_24_redis_check`. Local commit `434ec8e` (not pushed) plus the uncommitted release-candidate changes listed in §6.
**State:**
- nothing committed by engineering;
- nothing deployed;
- no production access or migration;
- the service is **off** in the release code;
- pages ship as **drafts**.

## 1. Verdict

| Question | Answer |
| --- | --- |
| Is the code a complete, tested release candidate? | **Yes.** All engineering work in the mandate is done. Full regression run 8: 3,286 tests, 3,270 passed. 15 of the 16 that did not pass are the same pre-existing Experian failures as on the clean baseline. The 16th was a test whose expectation predated the Savings Calculator gate; it was updated precisely and passes (`20-…` F-3). |
| Does it qualify as **production-deployment-ready**? | **Not yet.** Deployment is blocked by: <br>• **INF-02**: the runtime image is PHP 8.3, but the locked dependencies need PHP ≥ 8.4.1 (pre-existing); <br>• PR-02 to PR-05 (Operations evidence on production); <br>• CO-01 (commit) and DP-01 (Owner authorisation). <br>When these are done, the code can be deployed with the service off by `31-…`. |
| Is it **authorised for live customer applications**? | **No.** **0 of 13** approvals and **0 of 18** BV items have been approved. HV-00 is undecided, historical validation has not started, no lender policy exists, and the legal wording is unapproved. Go-live (gate G) needs every item in `34-…` §1. |

## 2. Assessment of the 11 areas (mandate §16)

| # | Area | Status | Evidence / reason |
| --- | --- | --- | --- |
| 1 | Engineering completion | **COMPLETE** | All modules built (`15-…`, `00-…`). This stage fixed 13 defects, each with a test that fails without its fix (§3). Code review done (§6). Independent review of the post-acceptance changes done. |
| 2 | Functional acceptance | **COMPLETE** (engineering) | Acceptance (`16-…`) plus this stage. Website and journeys checked at 3 widths (`28-…`). The off state is proven by tests. Business acceptance of the wording is part of area 5. |
| 3 | Financial calculation validation | **PENDING APPROVAL** | Engine = specification: 827/827, re-confirmed today (V01–V10 identical). No approval yet of BV-01 to 18 (`23-…`). |
| 4 | Historical lender-case validation | **BLOCKED** | HV-00 is not decided. Infrastructure is ready, 0 cases (`21-…`, `22-…`). |
| 5 | Legal and regulatory approval | **PENDING APPROVAL** | Decision pack A–L with DRAFT wording (`24-…`). Nothing is approved. |
| 6 | Credit and lender policy approval | **PENDING APPROVAL** | 0 lender policies; 0 lender documents; all lenders UNVERIFIED (`lender-policy/`). Credit items in `23-…`. |
| 7 | Operations readiness | **PENDING APPROVAL** | System support ready. 13 SOPs drafted; team email, training, retention, SMS and OPS-D1 undecided (`25-…`). |
| 8 | Security and privacy readiness | **PENDING APPROVAL** | High and Medium code findings fixed and tested. M3 erasure needs Legal/DPO; INF-04 and INF-05 need the Owner (`26-…`). |
| 9 | Infrastructure readiness | **BLOCKED** | INF-02 (PHP runtime) blocker; PR-02 to PR-05 need production evidence; INF-01 decision (`27-…`). |
| 10 | Deployment readiness | **BLOCKED** | Depends on areas 9, CO-01 and DP-01. Runbook, rollback and smoke tests ready (`31-…`, `32-…`, `33-…`). |
| 11 | Business go-live approval | **PENDING APPROVAL** | Gate G not requested. It needs areas 3–8 approved and `34-…` complete. |

## 3. What failed in this stage and how it was resolved

Every fix below has a test that was run against the unfixed code and failed, then passed with the fix.

| ID | Defect | Found by | Resolution |
| --- | --- | --- | --- |
| H1 | Staff upload trusted a browser-supplied file path: import and delete any private file | Security audit | `storeFiles(false)` + tamper prevention; only the uploaded temporary file is accepted |
| M1 | Unlimited loan rows: one request could exhaust a worker | Security audit | Rows capped at 20 everywhere |
| M2 | Email links built from the request's Host header | Security audit | Links built from `APP_URL` |
| L1 | Analysis tabs and report download did not check their own permissions | Security audit | Gated |
| L2 | Customer email unencrypted in the queue | Security audit | `ShouldBeEncrypted` |
| L3 | Analyser options not capped | Independent review | Capped at 3 |
| W-01 | **Savings Calculator would go public at deployment with the service off** (unapproved wording) | Website review | Public only while the service is live |
| W-02 | Full rollback left an orphan draft page | Migration rehearsal | Rename migration keeps timestamps |
| W-04 | Savings Calculator showed an end date for a card that never clears | Independent review | "Never at this payment" |
| W-05 | Preview while off linked to the hidden calculator | Independent review | Linked only while live |
| W-06 | Saved page wording kept the old "calculator" labels | Independent review | Migration renames stored shipped wording, reversibly |
| W-07 | Calculators Page settings could not be saved while DC is off (caused by W-01) | Independent review | Admin lists keep the calculator |
| PY | A Python bytecode file was tracked in git | Code review | Removed; `__pycache__/` and `*.pyc` ignored |

**Not fixed, by decision:**
- **W-03:** two non-DC sticky panels; the team rule records leaving them. Owner decision.
- **L4:** headline figures visible to anyone allowed to view a case; by design.
- **M3:** erasure; Legal/DPO decision.
- **INF-01, INF-02, INF-04, INF-05:** production configuration; Owner decisions. Patches are prepared for INF-01 and INF-02.

## 4. Regression results (summary of `20-…`)

| Run | Code | Tests | Passed | Not passing | Same as baseline? |
| --- | --- | ---: | ---: | ---: | --- |
| Baseline | Clean `084825b` (pre-project) | 3,011 | 2,996 | 15 (Experian external API) | — |
| 5 | Branch before this stage's fixes | 3,270 | 3,255 | 15 (same tests) | Yes |
| **8** | **Release candidate (all fixes)** | **3,286** | **3,270** | **16**: 15 Experian (same as baseline) + 1 site-preference test (F-3), updated and green on re-run | **Yes, for the 15**; F-3 explained and closed |

**Test inventory:**
- no baseline test is missing on the branch;
- 0 tests removed;
- no assertion weakened (the 2 changed lines in `SiteBrandingTest` follow the Owner's removal of the "Eligibility" menu link and are replaced by a test of the new behaviour).

## 5. Financial validation (summary of `23-…`)

- 827 of 827 figures match the independent reference.
- Formula version 1.0.0, unchanged.
- Savings Calculator defaults verified as configured: 5% held fixed; 14%; 3 years; 2%. **Not changed.**
- A lower EMI is never presented as a saving.
- Suitability, affordability, lender eligibility and lender approval are kept separate.

## 6. Code review and release candidate

### 6.1 Scope reviewed

- Everything between `084825b` and the working tree: commit `434ec8e` (310 files) plus the uncommitted and untracked changes.
- Areas checked: secrets, unintended changes, migrations, configuration, dependencies, routes, permissions, tests, documentation, build output and production configuration.

| Check | Result |
| --- | --- |
| Secrets and credentials | None found in the branch diff or untracked files. `.env` is not tracked. The seeder's admin email is pre-existing. Docs contain no local paths or personal data. |
| Dependencies | `composer.json`, `composer.lock`, `package.json`, `package-lock.json`: **unchanged** since `084825b` |
| Configuration | `config/debt_consolidation.php` (new). `config/filament-shield.php`: DC custom permissions (D-01). `.gitignore`: Python bytecode. No `.env` keys required. |
| Production configuration | `Dockerfile`, `docker/`, `docker-compose.yml`: **unchanged**. Two **proposed** patches in `release/` (not applied). |
| Migrations | 8, all additive (`27-…` §4.2). The upgrade was rehearsed from the clean baseline. |
| Routes | Added: 7 DC pages; portal DC routes (index, show, documents, reports, lender consent, consent withdrawal, notifications); the DC external API (read-only); `/calculators/debt-consolidation`. No existing route was changed or removed. |
| Permissions | DC Shield permissions plus the *Debt Advisor* role (`RoleSeeder`, `DebtConsolidationRoles`); approval and activation permissions ungranted by default |
| Shared code touched | `EnquiryType` (+1 case), `AppServiceProvider` (+1 rate limiter), `RoleSeeder` (+grant), `routes/console.php` (+1 scheduled job), `tests/TestCase.php` (in-memory-only guard), header/CSS/sticky offsets (see 6.2) |
| Tests | 3,011 → 3,286 tests; 0 removed; DC suites all pass |
| Documentation | 00–35 plus `lender-policy/`, `release/` and `validation/historical/`. Preserved files unchanged (§7). |
| Build output | `public/build` is git-ignored and built by the Docker image (`npm run build`). The local build (13:30) contains the new classes. |
| OCR / AI | None |

### 6.2 Changes in the working tree not made by this session

Made alongside this work on 26 Nov 2026 and reviewed here:

| Change | Files | Review |
| --- | --- | --- |
| "Debt Consolidation Analyser" rename of the loan-by-loan page; Savings Calculator links on DC pages | Migration `2026_11_26_072745…`, `DebtConsolidationPageController`, `DebtConsolidationPageContent`, `DebtConsolidationPagesSettings`, `debt-consolidation/show.blade.php`, analyser component | Correct apart from W-02, W-05 and W-06, which are now fixed |
| Header: the DC strip and header stick together; the `--site-header-height` variable; `top-below-header` / `scroll-mt-below-header` utilities applied to 7 sticky elements (including the credit-score check, customer login, lender comparison and portal) | `header.blade.php`, `app.css`, 7 views | Correct; tests updated (`SiteStylesheetTest`, `SiteBrandingTest`, `DebtConsolidationSitePreferenceTest`). W-03 is the only open point. |
| Desktop "Eligibility" menu link removed (Owner request) | `header.blade.php`, `SiteBrandingTest` | Intended; replaced by a test |
| Project rules | `.ai/rules/*` | Consistent with the code |

**Owner: please confirm these belong in this release before committing (CO-01).**

### 6.3 Independent review

A separate reviewer read every post-acceptance change and found 7 items:
- 5 fixed with tests: W-04, W-05, W-06, W-07, L3;
- 1 accepted: L4;
- 1 left for the Owner: W-03.

Areas the reviewer found clean:
- share-link and query tampering;
- Livewire property tampering;
- XSS;
- the upload fix;
- the row cap;
- email links;
- the off-state gating;
- the migration;
- the Hindi strings;
- the header and CSS offsets.

**Post-acceptance changes inside commit `434ec8e`** were outside the reviewer's scope (it reviewed only uncommitted work), so engineering reviewed them separately:
- **The DD/MM/YYYY date-of-birth field:** impossible dates such as 31/02 are refused by the server's `date` rule; resumed values pre-fill correctly; covered by a test.
- **The analyser's tab bar, empty state and sticky results column:** ARIA tab roles; the "Enter my first loan" focus target; the compiled `calc(100vh - var(--site-header-height) - 2rem)` was checked in the built CSS.

No defect was found in either.

### 6.4 Release candidate contents (for CO-01)

To commit, on top of `434ec8e`:
- **all modified files** in `git status`;
- **all untracked files** except anything under `storage/` or the scratch areas.

The untracked files are:
- the Savings Calculator view, component and test;
- migration `2026_11_26_072745…`;
- docs 17–35, `evidence/`, `lender-policy/`, `release/` and `validation/historical/`;
- 3 new `.ai/rules` files.

The deleted `validation/__pycache__/reference.cpython-314.pyc` must be committed as a deletion.

## 7. Preserved evidence

| File | md5 at acceptance | md5 now |
| --- | --- | --- |
| `16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md` | `c23a25c555eccb8f140cbc848316ce94` | `c23a25c555eccb8f140cbc848316ce94`: **unchanged** |
| `validation/DC-financial-validation-workbook.xlsx` | `62b04dc409d18c05c32bc26aed178399` | `62b04dc409d18c05c32bc26aed178399`: **unchanged** |
| `18-HISTORICAL-CASE-VALIDATION-PROCEDURE.md` | `d4f917c87d1710d1581458c64378344d` (at the start of this stage) | `d4f917c87d1710d1581458c64378344d`: **unchanged** |
| `validation/engine-results.json`, `cases.json`, `reference-results.json`, `comparison.json` | — | unchanged (`git status` shows no change) |

## 8. Exact Owner decisions required

| # | Decision | Where |
| --- | --- | --- |
| 1 | Confirm how production is built, and approve the PHP runtime patch or record the real build (**INF-02, blocker**) | `27-…` §6; `release/dockerfile-php-runtime.patch` |
| 2 | Explicit migration step: apply the entrypoint patch, or keep migrate-on-start under runbook discipline (INF-01) | `27-…` §4.4 |
| 3 | Restrict accepted hosts in nginx (INF-04); encrypt sessions (INF-05) | `27-…` §6 |
| 4 | Confirm the parallel changes belong in this release; then commit, or authorise engineering to commit (CO-01) | §6.2, §6.4 |
| 5 | W-03: move two non-DC sticky panels under the taller header, or leave them | `28-…` §4; `release/sticky-enquiry-panels.patch` |
| 6 | Fee to borrowers, with Legal (LA-03) | `24-…` F–H |
| 7 | Retention period for submitted cases (OD8), with Legal and Operations; erasure scope (M3) | `24-…` I; `26-…` §5 |
| 8 | SMS case-update templates: needed or not (OP-01) | `25-…` |
| 9 | Deployment authorisation (DP-01), after items 1, 4 and PR-02 to PR-05 | `31-…` |
| 10 | Go-live authorisation (GL-01), only after `34-…` §1 is complete | `34-…` |
