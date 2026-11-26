# Debt Consolidation — master project status

**Last updated:** 26 Nov 2026 · **Branch:** `feature/nov_24_redis_check` (uncommitted) · **Environment:** local only. Nothing is deployed; no production migration has been run.

> Resume instructions for any session: read this file, then `16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md`, then `03-architecture.md`. Items are ticked only when code, tests and docs exist.

**Current state: pre-production acceptance complete. Classification 2 — technically ready, business approvals pending.** Waiting for the business approvals (`16-…` §6) and the owner's explicit deployment authorisation. Nothing has been committed, deployed or switched on.

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
| 24 | **Pre-production acceptance** (independent reviews, financial validation workbook, baseline proof, deployment inspection). 24 defects found and fixed: 1 blocker, 6 major, 17 minor. | ✅ `16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md`, `validation/` |
| — | Business approvals (13 items) and deployment authorisation | ⏳ Pending — `16-…` §6 |

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
- Production deployment authorisation (`12-deployment.md`).

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

Final full run (25–26 Nov 2026, after the acceptance fixes): **3,243 tests, 3,227 passed**. All 16 failures are pre-existing (15 Experian, plus 1 rates test that fails after midnight IST), so there are no regressions. Debt Consolidation suites: 64 unit and 151 feature tests, all passing. Financial validation: 827 of 827 figures agree with the independent reference. Details are in `08-testing-report.md`; the approvals and remaining actions are in `16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md` §6 and §9.
