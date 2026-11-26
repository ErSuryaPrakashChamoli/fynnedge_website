# Debt Consolidation — master project status

**Last updated:** 26 Nov 2026 (release-candidate stage) · **Branch:** `feature/nov_24_redis_check`: local commit `434ec8e` (the Owner's, not pushed) plus uncommitted release-candidate changes · **Environment:** local only. Nothing is deployed; no production migration has been run.

> Resume instructions for any session: read this file, then `29-FINAL-PRODUCTION-READINESS-REPORT.md` and `30-RELEASE-GATE-MASTER-REGISTER.md`, then `16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md` and `03-architecture.md`. Items are ticked only when code, tests and docs exist.

**Current stage: Release candidate, awaiting human gates** (from 26 Nov 2026).
- Engineering is complete and regression-tested.
- The service is OFF in the release code and the pages are drafts.
- Nothing has been committed by engineering, deployed or switched on.
- **Owner decisions applied 26 Nov 2026:**
  - INF-01 explicit migrations;
  - INF-02 PHP 8.5 runtime;
  - W-03 sticky panels;
  - INF-05 encrypted sessions by default;
  - CO-01: Owner commit `65e315c` plus the follow-up commit, both local and not pushed;
  - DP-01 authorised;
  - OPS-D1 mandatory independent maker-checker (built);
  - M3 anonymisation and erasure (built);
  - INF-04 nginx hosts limited to fynnedge.com and www;
  - LA-03 Owner part: no fee to borrowers (Legal still confirms).
- **Production-deployment-ready: not yet.** Waits for PR-02 to PR-05 (Operations evidence) and a staging build of the PHP 8.5 image.
- **Authorised for live customer applications: no.**

| Gate group (`30-…`) | Status (26 Nov 2026) |
| --- | --- |
| A. Business and operational approvals (11) | **0 approved**; 10 pending; HV-01 blocked |
| B. Calculation validation BV-01 to BV-18 | **0 approved**; 17 pending; BV-13 blocked |
| C. HV-00 historical document-use permission | Pending (decision pack `21-…`) |
| D. Technical release gates | 9 of 9 complete (INF-02 needs a staging build to confirm) |
| E. Deployment prerequisites PR-01 to PR-05 | PR-01 complete (run 8: 3,286 tests, 3,270 passed; the 15 pre-existing Experian failures; 1 test updated for the Savings Calculator gate W-01 and re-run green); PR-02 to PR-05 blocked (production access) |
| F. Owner deployment authorisation (DP-01) | Authorised by the Owner on 26 Nov; waits for PR-02 to PR-05 and the staging build |
| G. Owner go-live authorisation (GL-01) | Pending |
| Technical validation evidence (not approval) | Engine vs independent reference: 827/827; re-confirmed on 26 Nov (V01–V10 identical) |

## Phases

| Phase | Scope | Status |
| --- | --- | --- |
| 0 | Existing system audit | ✅ `01-existing-system-audit.md` |
| Plan | Architecture, data model, sequence | ✅ `03-architecture.md` |
| 1 | Public pages + CMS integration (7 pages, routes, SEO, sitemap, FAQs, header link, page wording settings) | ✅ `10-cms-integration.md` |
| 1b | **Owner requirement (25 Nov 2026): Debt Consolidation given preference on the website**: header strip, nav after Loans, top of the mobile menu, footer, homepage feature (requirement 19) | ✅ `10-cms-integration.md` |
| 2 | Calculator suite (6 views) | ✅ `14-calculators-and-matching.md` |
| 3 | Enquiry + application journey (OTP, save/resume, duplicate detection, notifications) | ✅ `04-customer-journey.md` |
| 4 | Liabilities + documents (encrypted, versioned, manual verification; no OCR/AI) | ✅ |
| 5/6 | Financial engine, suitability, foreclosure, savings, break-even | ✅ `06-financial-engine.md` |
| 7 | PDF reports (8 types) | ✅ |
| 8 | Admin module (dashboard, 12 screens, 360° case view, roles) | ✅ `11-admin-documentation.md` |
| 9 | Lender policies, matching, lender applications | ✅ |
| 10 | Application tracking, debt closure, NOC | ✅ |
| 11 | Customer portal section + notifications | ✅ |
| 12 | Settings, scoring configuration, governance | ✅ |
| 18 | FynnAI-ready API (read-only, consent-gated) | ✅ `07-api.md` |
| 19 | Schema (18 tables), models, factories, policies, roles, retention | ✅ `05-database-design.md` |
| 20 | Security & compliance | ✅ controls; ⚠ legal items open — `09-security-compliance.md` |
| 22/23 | Testing, regression, readiness | ✅ `08-testing-report.md`, `15-final-completion-report.md` |
| 1c | **Owner requirement (26 Nov 2026): Debt Consolidation Savings Calculator** under Calculators (requirement 20) | ✅ `02-functional-requirements.md` |
| 24 | **Pre-production acceptance** (independent reviews, financial validation workbook, baseline proof, deployment inspection). 24 defects found and fixed: 1 blocker, 6 major, 17 minor. | ✅ `16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md`, `validation/` |
| 25 | **Business validation and sign-off**: tracker, historical-case procedure, approver checklists | ⏳ In progress: documents ready; 0 of 13 approvals and 0 of 18 BV items signed — `17-…`, `18-…`, `19-…` |
| 26 | **Release candidate (final master mandate)**: full regression vs the clean baseline; security audit and fixes; independent review; migration rehearsal; lender-policy readiness; historical-validation infrastructure and HV-00 pack; legal, operations, infrastructure and website reports; runbook, rollback, smoke tests, go-live checklist, gate register | ✅ engineering complete; ⏳ human gates open: `20-…` to `35-…`, `lender-policy/`, `release/`, `validation/historical/` |

## Outstanding decisions (business / legal sign-off; defaults in place and configurable)

| # | Decision | Default used | Where to change |
| --- | --- | --- | --- |
| OD1 | GST on foreclosure and processing charges | 18 % | Debt Consolidation Settings |
| OD2 | Suitability weights (30/25/20/15/10) and thresholds | Mandate weights; thresholds in `06-financial-engine.md` §6 | Scoring Configuration (new version) |
| OD3 | Card minimum obligation for FOIR when no payment is entered | 5 % of balance | Debt Consolidation Settings |
| OD4 | Lender debt consolidation policies | **None seeded** — enter from lenders' own documents | Loan Products & Lenders → Debt Consolidation Policies |
| OD5 | RBI Digital Lending (LSP) applicability; DPDP notice/consent wording | Draft wording, flagged | `09-security-compliance.md` §3 |
| OD6 | Fee / lender-commission disclosure wording | "Any fee will be disclosed before you apply" | Debt Consolidation FAQs page |
| OD7 | Indicative terms of the standard comparison options | PL 14 %/60 m/2 %, LAP 10.5 %/120 m/1 %, UBL 17 %/48 m/2 %, SBL 11.5 %/84 m/1 % | Debt Consolidation Settings |
| OD8 | Retention period for submitted cases | Kept (no automatic deletion) | Business record-keeping policy |
| OD9 | Whether the portal must show all matching lenders' offers (multi-lender LSP rule) | Shows advisor-shortlisted lenders only | `09-security-compliance.md` §3.1(b) |

## External dependencies

- Lender debt consolidation policies and contacts (business).
- Legal review of consent, disclaimer and fee wording (OD5, OD6, OD9).
- Customer SMS updates need DLT-registered templates with the SMS provider (only OTP SMS exists today); email and in-portal updates work.
- A team email address for new-case alerts (Debt Consolidation Settings).
- A parallel accuracy run by advisors on 10–20 anonymised past cases, against real lender foreclosure letters, before accepting real applications (HV-01 / BV-13 in `16-…`).
- Operations to confirm: production mail transport; that `schedule:run` runs (it is not in the Docker image); persistent, backed-up `storage/app/private`.
- Production deployment authorisation (`31-DEPLOYMENT-RUNBOOK.md`; `12-deployment.md` is the earlier version).
- **The Docker runtime image (PHP 8.3) cannot run the locked dependencies (PHP ≥ 8.4.1).** This is pre-existing. Confirm the real production build or approve `release/dockerfile-php-runtime.patch` (INF-02, `27-…`).
- Legal/DPO decision HV-00 before any historical document is collected (`21-…`).

## Accepted limitations

- Calculations use the figures customers or advisors enter; nothing is read from documents (by design — OCR/AI excluded).
- Credit score used in matching is self-declared (labelled so); bureau pulls stay in the existing credit-bureau module.
- "External analysis" consent exists for the future FynnAI API but no screen collects it yet.
- PDFs are English only (dompdf cannot shape Devanagari), as for the existing credit-report PDF.
- A signed-in customer with an open draft continues it. To start the other applicant type they finish or withdraw it, or an advisor opens it.
- A loan added after disbursement gets no closure tracker; staff mark it "Exclude".
- Super admin is exempt from maker-checker.

## Known defects / technical debt

- **Pre-existing (not caused by this project):**
  - `tests/Feature/Experian/ExternalExperianApiTest.php`: 15 failures; the `/api/v1/external/experian/*` routes are not registered in `routes/external.php`.
  - `tests/Feature/Rates/PublicRateDisplayTest.php`: 1 failure between 00:00 and 05:30 IST. The rate "As listed on …" label uses the UTC date. Both were verified on a clean checkout of `084825b`.
- None open in Debt Consolidation: all 24 acceptance defects are fixed and tested (`16-…` §5).

## Test results

**Final (run 9, 26 Nov 2026, after the Owner decisions):** 3,294 tests, 3,279 passed. The 15 failures are exactly the clean baseline's Experian set.

**Release candidate (run 8, 26 Nov 2026):** 3,286 tests, 3,270 passed. 15 of the 16 that did not pass are the same `ExternalExperianApiTest` failures as on the clean baseline `084825b` (3,011 tests, 2,996 passed, 15 failures). The 16th was a site-preference test whose expectation predated the Savings Calculator gate (W-01). It was updated to assert the exact new difference and passes (12/12). No test was removed and no assertion was weakened (`20-REGRESSION-CLOSURE-REPORT.md`; output in `evidence/`).

Earlier: acceptance run 4 (25–26 Nov 2026): 3,243 tests, 3,227 passed, 16 pre-existing failures. Financial validation: 827 of 827 figures agree with the independent reference.
