# 35 — Known limitations and risks

**For:** the Owner and every approver. This lists what the release does **not** do, and the risks that remain after this stage's fixes. Each item names its owner and where it is tracked.

## 1. Functional limitations (by design or scope)

| # | Limitation | Why | Effect | Owner / tracker |
| --- | --- | --- | --- | --- |
| L-01 | No OCR or AI document reading; every figure is typed and verified by people | Excluded by mandate | Advisor workload; data-entry errors are caught by verification (SOP-03) | — (scope) |
| L-02 | FynnAI is not connected; only a read-only, consent-gated API exists | Future integration | None for go-live | — (scope) |
| L-03 | Credit score is **declared** by the customer, not pulled from a bureau, for matching | No bureau pull in the DC flow | A match can be optimistic; reason lines say "not bureau-verified" | Credit (LP-01) |
| L-04 | Lender matching returns nothing until partner policies are entered and approved | No lender documents yet | Advisors cannot shortlist lenders | Credit (LP-01) |
| L-05 | Some lender criteria cannot be held in the DC policy: maximum income, part-prepayment rules, lock-in, fixed fees | Data model | Advisors must check them by hand before shortlisting | Credit (`LP-C` P-09) |
| L-06 | The engine estimates interest monthly. Lenders may use daily interest, broken-period interest, or EMIs rounded up. | Formula v1.0.0 specification | Small differences from lender letters; to be measured by HV-01 (D3 class) | Head of Advisory + Finance (HV-01) |
| L-07 | The Savings Calculator holds the card payment fixed (minimum due at the start) | Approved default to be confirmed (BV-04) | Savings tend to be understated, not overstated (`23-…` §3.2) | Credit (BV-04) |
| L-08 | No SMS case updates; only OTP SMS | DLT templates not decided | Customers rely on email and the portal | Operations (OP-01) |
| L-09 | No complaints or escalation module; handled by procedure (SOP-13) | Scope | Depends on staff discipline | Operations |
| L-10 | Second-person checks on quotations, closure payments and closure verification are procedural, not enforced | Scope | One person could do both steps | Operations (OPS-D1) |
| L-11 | No customer-data erasure function; abandoned drafts are only soft-deleted | Awaits Legal/DPO decision | DPDP erasure requests handled manually by engineering until built | Legal/DPO (`26-…` M3) |
| L-12 | Customers see a lender as "Your advisor suggests…", not as a multi-lender offer list with APR or KFS | Depends on LA-01 | May need a change if Legal decides FynnEdge is a multi-lender LSP | Legal (LA-01) |

## 2. Release and infrastructure risks

| # | Risk | Likelihood / impact | Mitigation | Owner |
| --- | --- | --- | --- | --- |
| R-01 | **The runtime image is PHP 8.3; the locked dependencies need ≥ 8.4.1** (pre-existing) | Certain if built as committed / site down | Confirm the real production build; apply `release/dockerfile-php-runtime.patch`; staging build and part A of the smoke test | Owner / Operations (INF-02) |
| R-02 | Container start runs migrations automatically, including on unplanned restarts | Medium / schema changes without a decision | Explicit-migration patch (INF-01), or runbook discipline (`31-…` §3b) | Owner |
| R-03 | Production state unknown to engineering (migrations, `.env`, CMS edits) | Medium | `31-…` §1.1 and §1.2 read-only checks; `migrate --pretend` on a copy | Operations |
| R-04 | MySQL rollback of the release batch was rehearsed at acceptance for migrations 1–7. In this stage only SQLite was available (it cannot run migration 5's foreign-key drop). | Low | Rehearse `migrate` and `migrate:rollback --pretend` on a production copy (`31-…` §1.2) | Operations |
| R-05 | `APP_KEY` loss makes every DC document and report unreadable | Low / severe | Separate key backup (PR-05) | Operations |
| R-06 | Scheduler not running in production | Unknown | PR-03 | Operations |
| R-07 | Mail transport not confirmed | Unknown | PR-02; part C of the smoke test checks the links and delivery | Operations |
| R-08 | Any host header accepted by nginx (site-wide) | Low (DC emails fixed) | INF-04 | Owner |
| R-09 | Sessions not encrypted (site-wide) | Low | INF-05 | Owner |
| R-10 | No monitoring or alerting in the repository | Medium | INF-06 | Operations |
| R-11 | Two non-DC sticky enquiry panels sit further under the taller sticky header (W-03) | Certain / cosmetic | One-line class change each, awaiting decision | Owner / Marketing |

## 3. Pre-existing defects outside Debt Consolidation (not caused by this release)

| # | Defect | Evidence | Effect on this release |
| --- | --- | --- | --- |
| P-01 | **External Experian API routes are missing.** `ExternalExperianApiTest` (15 tests) calls `/api/v1/external/experian/*`, but no route is registered in `routes/external.php`, so the tests get 404. A request class for it exists (`StoreExternalExperianCheckRequest`). | Same 15 failures on the clean baseline `084825b` and on the branch; the branch does not touch Experian (`20-…`) | None on DC. The Experian external API does not work for LMS callers. Engineering for the Experian module should be told. |
| P-02 | `PublicRateDisplayTest` fails between 00:00 and 05:30 IST (UTC/IST date boundary) | Reproduced on the clean baseline at acceptance; passes outside that window (runs 5 and 7) | None on DC; a test-timing defect in the rates module |
| P-03 | `/contact` meta description is 36 characters | Crawl (`28-…`) | SEO only |

## 4. Validation limits

| # | Limit | Status |
| --- | --- | --- |
| V-01 | The 827/827 figures prove the engine matches its specification, **not** that the specification matches lenders | HV-01 needed (HV-00 first) |
| V-02 | No real lender document has been compared with the engine | `22-…` BLOCKED |
| V-03 | No approval exists for any of the 13 approvals or 18 BV items | `17-…`, `30-…` |
| V-04 | Lighthouse performance not re-measured for the DC pages (local timings are not comparable with production) | Measure after go-live |
