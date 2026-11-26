# 30 — Release gate master register

**For:** the Owner and every approver. This is the single matrix of every gate between the release candidate and live customer applications.

**Rules:**
- IDs from `16-…` and `17-…` are preserved.
- **No gate is marked COMPLETE without evidence.**
- Engineering evidence is **not** an approval. Approval rows stay PENDING until the named approver decides, with a reference.

**Status values:**
- **COMPLETE**: verified with evidence.
- **PENDING APPROVAL**: awaiting an authorised decision.
- **BLOCKED**: a prerequisite is missing.
- **FAILED**: the requirement is not met.
- **NOT APPLICABLE**: justified.

**Last updated:** 26 Nov 2026 (release-candidate stage; Owner decisions of 26 Nov applied).

**Owner reply "approved all" (26 Nov 2026).** This was applied to the decisions that are the Owner's own:
- INF-01, INF-02 and W-03 (patches applied);
- INF-05 (sessions encrypted by default in `docker-compose.yml`);
- CO-01: the Owner committed the release candidate as `65e315c`; the follow-ups were committed after it, locally;
- DP-01 (authorised, waiting for its prerequisites).

**It was not recorded as approval** of:
- any A, B or C item: each needs its named approver and, for most, a specific choice (`17-…`, `19-…`);
- INF-04 (nginx hosts), which needs the production hostnames and proxy behaviour first.

## Summary by gate group

| Group | Meaning | Items | Complete | Pending approval | Blocked |
| --- | --- | ---: | ---: | ---: | ---: |
| A | Business and operational approvals | 11 | 0 | 10 | 1 (HV-01) |
| B | Calculation validation items | 18 | 0 | 17 | 1 (BV-13) |
| C | HV-00 historical document-use permission | 1 | 0 | 1 | 0 |
| D | Technical release gates (code review, fixes, commit) | 9 | 9 | 0 | 0 |
| E | Deployment prerequisites | 5 | 1 (PR-01) | 0 | 4 |
| F | Owner deployment authorisation | 1 | 0 (authorised; waits for E) | 0 | 1 (PR-02 to PR-05) |
| G | Owner go-live / activation authorisation | 1 | 0 | 1 | 0 |

## A. Business and operational approvals

| Gate | Description | Owner | Evidence available | Status | Blocking dependency | Completion date | Approver | Approval reference |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| LA-01 | Regulatory position (RBI digital lending: LSP / DSA / referral), multi-lender display, money flows, grievance | Legal / Compliance | `24-…` A, B; `09-…` §3 | PENDING APPROVAL | Partner agreements (`LP-C` P-03) | | | |
| LA-02 | Consents, notices (DPDP), disclaimers, warnings (with BV-18), privacy policy and terms; EN + HI | Legal / DPO + Compliance | `24-…` C, D, E, J, K (DRAFT wording) | PENDING APPROVAL | LA-01, LA-03 | | | |
| LA-03 | Borrower fee: whether any; disclosure; refund policy | Legal + Owner | `24-…` F, G, H | PENDING APPROVAL | LA-01 | | | |
| FA-01 | Financial assumptions (GST 18%, card 5%, 60 months, 1.5 points, 90 DPD) and BV-01 to 06, BV-15 | Finance + Credit | `23-…`; workbook; 827/827 | PENDING APPROVAL | HV-01 (final sign-off of BV-01, BV-06) | | | |
| FA-02 | Methods: loan sizing, net benefit, break-even, part-prepayment (BV-07, 09, 10, 11) | Finance + Head of Advisory | `23-…`; Check C | PENDING APPROVAL | HV-01 (BV-09) | | | |
| SC-01 | Suitability score (BV-14) | Head of Advisory + Compliance | `23-…`; Check D | PENDING APPROVAL | SC-02, FA-02 | | | |
| SC-02 | Affordability bands (BV-12) | Credit | `23-…`; Check D | PENDING APPROVAL | — (can start now) | | | |
| LP-01 | Partner lender policies entered from documents and approved by a second person | Credit (maker + checker) | `lender-policy/LP-A`, `LP-B`, `LP-C` (0 policies; 0 documents) | PENDING APPROVAL | LP-02 (BV-16), LA-01, lender documents | | | |
| LP-02 | Standard options, Savings Calculator defaults, route matrix (BV-16, BV-17) | Credit | `23-…` §3.2 | PENDING APPROVAL | — (can start now) | | | |
| HV-01 | Historical case validation accepted (BV-13) | Head of Advisory + Credit | Infrastructure (`22-…`); 0 cases | **BLOCKED** | HV-00; source documents | | | |
| OP-01 | Team email, training, retention (OD8), SMS decision, SOPs incl. SOP-05 (BV-08), OPS-D1 | Operations + Owner | `25-…` (13 SOPs, draft) | PENDING APPROVAL | LA-02 (training wording) | | | |

`CO-01` and `DP-01` (also among the 13 approvals of `16-…`) are placed in groups D and F below, where they belong.

## B. Calculation validation items

| Gate | Description | Owner | Evidence available | Status | Blocking dependency | Completion date | Approver | Approval reference |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| BV-01 | EMI and paisa rounding | Credit / Finance | Check A; 827/827 | PENDING APPROVAL | 3 real schedules (HV-01) | | | |
| BV-02 | Flat-rate detection, 1.5 points | Credit | V10 | PENDING APPROVAL | Real flat-rate statement | | | |
| BV-03 | Partial details; 60-month assumption | Credit | V01, V06 | PENDING APPROVAL | — | | | |
| BV-04 | Cards (incl. Savings Calculator 5% held fixed) | Credit | V02; 18 calculator tests | PENDING APPROVAL | 2 card statements | | | |
| BV-05 | Interest-only debts | Credit | V03, V04 | PENDING APPROVAL | OD and gold-loan schedules | | | |
| BV-06 | Foreclosure estimate and GST | Finance + Legal | Check B; V07 | PENDING APPROVAL | 5 real letters (HV-01) | | | |
| BV-07 | Part-prepayment | Credit | V05 | PENDING APPROVAL | 2 lenders' practice | | | |
| BV-08 | Quotation procedure | Operations | SOP-05 | PENDING APPROVAL | — | | | |
| BV-09 | Loan sizing, fee, GST, shortfall | Credit / Finance | Check C; brute force | PENDING APPROVAL | 3 sanction letters (HV-01) | | | |
| BV-10 | Net financial benefit | Head of Advisory | Check C | PENDING APPROVAL | — | | | |
| BV-11 | Break-even | Head of Advisory | Reference match | PENDING APPROVAL | — | | | |
| BV-12 | FOIR / DSCR bands | Credit | Check D | PENDING APPROVAL | Partner norms | | | |
| BV-13 | Historical case validation | Head of Advisory + Credit | `22-…` | **BLOCKED** | HV-00 | | | |
| BV-14 | Suitability score | Head of Advisory + Compliance | Check D | PENDING APPROVAL | SC-02, BV-10 | | | |
| BV-15 | Not consolidatable now | Credit | V09 | PENDING APPROVAL | Partner rules | | | |
| BV-16 | Route matrix | Credit | Config reviewed | PENDING APPROVAL | — | | | |
| BV-17 | Standard options; calculator defaults 14% / 3 years / 2% | Credit | Code verified | PENDING APPROVAL | Partner rate cards | | | |
| BV-18 | Warning wording, EN + HI | Compliance | Tests; screenshots | PENDING APPROVAL | With LA-02 | | | |

## C. HV-00 historical document-use permission

| Gate | Description | Owner | Evidence available | Status | Blocking dependency | Completion date | Approver | Approval reference |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| HV-00 | Permission to use redacted documents from past cases for internal validation: 9 points incl. retention option | Legal / DPO | Decision pack `21-…`; workbook gate (CLOSED) | PENDING APPROVAL | — | | | |

## D. Technical release gates

| Gate | Description | Owner | Evidence | Status | Blocking dependency | Completion date | Approver | Approval reference |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| TG-01 | Code review of the branch diff: secrets, unintended changes, migrations, config, dependencies, routes, permissions, tests, docs, build output | Engineering | `29-…` §6 | COMPLETE | — | 26 Nov 2026 | Engineering evidence (not an approval) | `29-…` §6 |
| TG-02 | Security findings H1, M1, M2, L1, L2 (queue) fixed with failing-first tests | Engineering | `26-…` §2 | COMPLETE | — | 26 Nov 2026 | Engineering evidence | `26-…` |
| TG-03 | Financial engine unchanged since validation; V01–V10 re-run identical | Engineering | `23-…` §1 | COMPLETE | — | 26 Nov 2026 | Engineering evidence | `23-…` |
| TG-04 | Migration upgrade rehearsal from the clean baseline; rollback defect fixed | Engineering | `27-…` §4.3 | COMPLETE (SQLite; MySQL-on-copy in runbook §1.2) | — | 26 Nov 2026 | Engineering evidence | `27-…` |
| TG-05 | Release-gate defects fixed: Savings Calculator hidden while off (W-01); rollback orphan page (W-02); review findings W-04 to W-07 and L3 | Engineering | `28-…` §4; `26-…` | COMPLETE | — | 26 Nov 2026 | Engineering evidence | `28-…`, `26-…` |
| TG-08 | Independent read-only review of every change after acceptance | Engineering (separate reviewer) | `29-…` §6.3: 7 findings, of which 5 fixed with tests, 1 accepted (L4), 1 open owner decision (W-03) | COMPLETE | — | 26 Nov 2026 | Engineering evidence | `29-…` |
| TG-06 | INF-02: runtime image must run the locked dependencies (PHP ≥ 8.4.1) | Owner / Operations | Patch applied: `Dockerfile` runtime `php:8.5-fpm-alpine`. A staging build is still required (`31-…` §1.3; no container engine on the development machine) | COMPLETE (code); staging build pending | — | 26 Nov 2026 | Owner | Owner, chat 26 Nov 2026 ("approved all") |
| TG-07 | INF-01: explicit migration step | Owner | Patch applied: `docker/entrypoint.sh` migrates only with `RUN_MIGRATIONS=true` and refuses to start while migrations are pending; logic checked against a database with a pending migration | COMPLETE | — | 26 Nov 2026 | Owner | Owner, chat 26 Nov 2026 ("approved all") |
| CO-01 | Review and commit the release candidate, including the parallel changes (`29-…` §6.2) | Owner / tech lead | Committed by the Owner as `65e315c` (26 Nov 2026 16:41, release candidate incl. parallel changes). The Owner-approved follow-ups (INF-01, INF-02, W-03, INF-05, doc updates) were committed after it. Neither is pushed. | COMPLETE (local; push not requested) | — | 26 Nov 2026 | Owner | `65e315c` and the following commit; Owner, chat 26 Nov 2026 ("approved all") |

## E. Deployment prerequisites

| Gate | Description | Owner | Evidence | Status | Blocking dependency | Completion date | Approver | Approval reference |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| PR-01 | Full regression suite on the release candidate, compared with the clean baseline | Engineering | `20-…` (run 7) | COMPLETE | — | 26 Nov 2026 | Engineering evidence | `20-…` |
| PR-02 | Production mail transport and delivery test | Operations | Procedure `27-…` §5 | **BLOCKED** | Production access | | | |
| PR-03 | Scheduler running and monitored | Operations | Procedure `27-…` §5 | **BLOCKED** | Production access | | | |
| PR-04 | Persistent, backed-up private storage | Operations | Procedure `27-…` §5 | **BLOCKED** | Production access | | | |
| PR-05 | Pre-deployment backup of DB, files and `APP_KEY`, with a restore check | Operations | Procedure `31-…` §2 | **BLOCKED** | Taken at the deployment window | | | |

## F. Owner deployment authorisation

| Gate | Description | Owner | Evidence | Status | Blocking dependency | Completion date | Approver | Approval reference |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| DP-01 | Written authorisation to deploy (the service stays off; the release runs 8 additive migrations in an explicit step) | Owner | `31-…` | **Authorised by the Owner; not executable yet**: waits for PR-02 to PR-05 and the staging build (`31-…` §1). Engineering has no production access; Operations deploys. | PR-02 to PR-05; staging build | | Owner | Owner, chat 26 Nov 2026 ("approved all") |

## G. Owner go-live / activation authorisation

| Gate | Description | Owner | Evidence | Status | Blocking dependency | Completion date | Approver | Approval reference |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GL-01 | Written authorisation to switch the service on for live customer applications | Owner | `34-…` G-01 to G-12 | PENDING. The Owner's blanket approval cannot take effect: under the Owner's own rule the service goes live only after every gate in A, B and C is approved, and none is yet. | Every gate in A, B, C, and F done; `34-…` §1 complete | | | |
