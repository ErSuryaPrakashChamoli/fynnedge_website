# 38 — Content restoration verification

**Status (27 Nov 2026):** safeguards and the recovery tool are verified, and the full restore is **rehearsed on a private copy of the production database** (export of 27 Nov, 06:31 UTC). **Production content is not yet restored**: that needs Owner approval and production access (`37-…` §4). §1 is the checklist for after the production restore, with the rehearsal result beside each item.

## 1. Existing website: post-restore checklist (production)

Run after step 6 of `37-…` §4.

**How the rehearsal was run:**
- 102 tables were imported into `fynnedge_prod_restore` on a private MySQL 8.4 instance: localhost only, session timezone +05:30 like production, separate from the working `fynnedge` database and server.
- The current code ran against it unchanged; its migrations match production's 162 exactly.
- Preview, then `--apply --hide-created`, window `13:05:00`–`13:09:59` UTC:
  - 480 records and 819 fields restored;
  - 39 created records hidden;
  - a 2 MB backup of the replaced values written first.

| # | Check | How | Rehearsal (copy) | Production |
| --- | --- | --- | --- | --- |
| 1 | The preview after the restore shows **0 fields to restore** for the same window | Re-run the command without `--apply` | ✅ 0 fields; all 819 now listed as "changed again after the window; current value kept", so a second run cannot undo anything | ☐ |
| 2 | The 11 product pages (#2–#12) show the pre-incident summary and body, and their "Updated" date is no longer 26 Nov | Open `/loans/<slug>` | ✅ own summaries back; published 8 Sep; `personal-loans` (#1) untouched | ☐ |
| 3 | The 12 moved pages return 200 again under `/loans/personal-loans/…`: 1/3/5/10 lakh, salaried, self-employed, women, senior citizens, medical, travel, home renovation, debt consolidation | Request each URL | ✅ all 12 back on product #1 and published | ☐ |
| 4 | The duplicate `/loans/personal-loan` and its 25 pages are hidden (404), or the Owner decided to keep the product (staff edited it on 27 Nov) | Owner decision | ✅ product and 25 pages Draft (copy predates the staff edit) | ☐ |
| 5 | `/about`, `/privacy-policy`, `/terms`, `/disclaimer`, `/grievance`, `/careers` and `/credit-report-terms` show the approved wording | Legal sign-off | ✅ earlier bodies back, none carries the starter "November 26, 2026" date; About title back to "About" | ☐ |
| 6 | The 10 articles (#1–#10) show their earlier text and 8 Sep date, so they no longer top `/resources` | Open `/resources` | ✅ (they existed and were published before, so they stay published) | ☐ |
| 7 | The sitemap no longer carries a `2026-11-26T13:05` or `T13:09` `lastmod` on restored URLs (expected: the restore time instead) | `/sitemap.xml` | n/a on the copy | ☐ |
| 8 | Homepage and FAQ page have no starter FAQs, starter testimonials or "DEMO Lender" entries | Open `/`, `/faqs` | ✅ testimonials #1–#6 Draft; FAQs #158–#165 Draft and #12–#14, #20 back to Draft; lenders #50–#53 and offer #359 Inactive | ☐ |
| 9 | Marketing and SEO roles have the permissions the Owner expects | Admin → Roles | not restorable (`37-…` step 7) | ☐ |
| 10 | Navigation, footer, banners, page SEO, redirects and settings are unchanged | Spot check | ✅ no row written in either run (`36-…`) | ☐ |
| 11 | Hindi (`?lang=hi`) on restored pages is the earlier Hindi | Spot check | restored with the other fields where the seeder changed it | ☐ |
| 12 | The manual items in `37-…` step 7 are done (eligibility rule sets #3 and #4 off, job opening #8 closed, Owner decisions recorded) | Admin | n/a | ☐ |

The rehearsal preview is saved as `evidence/2026-11-27-restore-rehearsal-preview.txt`.

## 2. Debt Consolidation preservation

| Check | Result |
| --- | --- |
| DC code, migrations, tests and docs unchanged | ✅ `git diff` touches only `DatabaseSeeder.php` and `.ai/rules/`, plus the new command, tests and docs 36–38. No DC file changed. |
| Local DC data unchanged | ✅ Local row counts for all 102 tables are identical before and after this work. The 7 DC pages are published; 25 DC FAQs are published; `debt_consolidation = {"enabled": true}`. |
| The restore never touches DC content | ✅ Tested: a DC page changed by an unattended process in the window is left as it is. On local MySQL the preview listed all 7 DC pages and 25 DC FAQs as "never restored". |
| The DC service was not switched on or off, and no DC page was published or unpublished | ✅ No such action was taken anywhere. |
| Production DC state | Not changed by this work. Live today: `/debt-consolidation` 200; the 7 DC pages were published at 13:22 UTC, after the incident. |
| `PublishDebtConsolidationPagesSeeder` (`79a9324`, branch `production` only) | Untouched; it is scoped to DC pages and FAQs. |

## 3. Test results (27 Nov 2026)

Tests ran on the in-memory SQLite database only. `bootstrap/cache/config.php` exists again, so each run pointed `APP_CONFIG_CACHE` at a non-existent file, and the `TestCase` guard was still active.

| Run | Result |
| --- | --- |
| `tests/Feature/DatabaseSeederTest.php`, new | 4/4 pass. It failed 3/4 before the guard existed, as expected. |
| `tests/Feature/RestoreContentFromAuditLogCommandTest.php`, new | 5/5 pass. The fifth test replays two runs (13:05:44 and 13:09:08) and proves the value from before the **first** run is restored. Changing the command to keep the latest `old` makes it fail with `2026-11-26 13:05:44`, so it guards the behaviour production needs. |
| DC (`tests/Feature/DebtConsolidation`, `tests/Unit/DebtConsolidation`) plus 34 website/CMS files (pages, loans, landing pages, articles, FAQs, sitemap, SEO, navigation, homepage, seeders) | **627/627 pass** (2,872 assertions) |
| Full suite | Not run in this session. The last full run (26 Nov, run 9): 3,294 tests, 3,279 passed, with 15 pre-existing Experian external-API failures. |

What the new tests prove:

**`DatabaseSeeder`:**
- On a database with real content and no confirmation, it refuses and lists the existing rows; nothing changes.
- On production it refuses even with `--force`, and never asks.
- It overwrites only after an explicit "yes".
- A freshly migrated database still seeds without a prompt.

**Restore command:**
- It reproduces the incident with the real starter seeders.
- When two runs fall in the window, it restores the value from before the first run.
- It restores text, JSON lists, status and dates exactly, and moves a landing page back to its product.
- It keeps a field fixed by staff the next morning.
- It ignores a change by a signed-in user inside the window.
- It never touches a DC page.
- It hides what the seeder created, except a record staff edited since.
- It writes a backup first.
- Preview changes nothing; declining the confirmation changes nothing; a missing window fails.

## 4. Before and after

| | Before this work | After this work |
| --- | --- | --- |
| Bare `db:seed --force` on production | Overwrites all content silently | Refused, with the list of existing rows |
| Bare `db:seed` locally over content | Overwrites silently | Shows the existing rows and asks; non-interactive runs refuse |
| Undoing an unattended overwrite | Manual, or a whole-database restore | Field-level revert from `audit_logs`: preview, confirmation, backup, DC excluded |
| Production content | Starter text on 464 URLs; 3+ URLs 404 | **Unchanged: awaiting the approved restore** |
| Local content | Starter content plus DC | Unchanged; forensic copy taken |

Forensic copies (outside the repo, owner-only permissions): `~/fynnedge-backups/2026-11-27-content-incident/` holds:
- a full local dump and table counts from before and after this work;
- git state and an all-refs bundle;
- today's snapshot of 32 affected production pages and the sitemap.

## 5. Outstanding issues

1. **Production restore not done.** Rehearsed on a copy and ready. It still needs a fresh production backup, deployment of the command, and Owner approval (`37-…` §4). Use the window `13:05:00`–`13:09:59` UTC.
2. **Name the operator** of the two `db:seed` runs (13:05:43 and 13:09:07 UTC) from the production server's shell history.
3. **Ask the host for a backup or snapshot from before 2026-11-26 13:05 UTC.** It is needed only for 4 calculator page bodies, the flexi product's SEO title and description, and document types #6 and #7. Without one, re-enter them by hand.
4. **Duplicate `personal-loan` product:** the Owner decides whether to hide it. Staff edited it on 27 Nov; check whether that edit was meant for `personal-loans`. 5 customer journeys ran on it; keep that data either way.
5. **Legal pages** are showing starter wording on production now. Restore them first, or temporarily unpublish them on Legal's advice.
6. **Local database** is starter content until a production export is imported (`37-…` §6).
7. **`bootstrap/cache/config.php` keeps reappearing** (cause unknown, outside the app). The `TestCase` guard blocks a wipe, but the cause should be found.
8. **Production has dev dependencies installed.** `DatabaseSeeder` used `fakerphp/faker` (`require-dev`): the DEMO lender names are faker output. Both runs got through testimonials, and the first reached the newsletter seeder (13:05:49). Make sure production installs with `--no-dev`.
9. **Committed, not pushed or deployed:** commit `b7916cb` on `feature/nov_27_backup`. It sits directly on `origin/production` (`79a9324`) and changes no migrations, so releasing it is a fast-forward of `production`. The operator deploys it, then runs `release/content-restore-2026-11-27/run.sh`.
10. The two full-seed tests in `DatabaseSeederTest` take about 1 minute each.
11. **DEMO lenders and eligibility rules are live on production:**
   - 4 "DEMO Lender" rows and DEMO offer #359 are active.
   - Rule sets #3 and #4 are active.
   - One eligibility result has been produced against them since the incident, so a customer may have been shown a DEMO lender.

   The restore deactivates the lenders and the offer; the rule sets need the manual step in `37-…` step 7.
12. **A real debt consolidation application exists on production** (26 Nov 20:52 UTC, consent recorded). `00-…` says live applications are not authorised. The Owner decides whether to pause the DC service and how to handle this customer.
13. **Private rehearsal copy:** the production export sits in `~/snap/mysql-workbench-community/13/dumps/Dump20261127`, and an imported copy in Claude's session scratchpad (server stopped). Both hold customer data; delete them once the production restore is signed off.
14. **Union Bank of India is marked inactive but has 4 active offers, which still take part in eligibility.** The eligibility engine checks offer status, not lender status. This predates the incident and has no effect on the DEMO lenders: 51–53 have no offers, and #50's only offer is hidden by the restore. The tests `EligibilityEngineTest` "leaves an inactive lender offer out" and "stops using a rule set once it is archived" cover what the restore relies on. The Owner decides whether inactive lenders' offers should also be excluded; that would change real eligibility results.

## 6. Owner decisions of 27 Nov 2026

| # | Decision | Where it is carried out | Status |
| --- | --- | --- | --- |
| 1 | Selective restore, window 13:05:00–13:09:59 UTC, with mandatory controls | `run.sh` phases 2–8 (`37-…` §4) | ☐ production |
| 2 | Pause new DC applications now; keep existing applications and journeys | `run.sh pause-dc`, or admin → Debt Consolidation Settings → switch off "Accept individuals" and "Accept businesses". Tested: pages stay live; the assessment page shows "Online assessments are paused"; new enquiries and applications are refused; existing cases stay visible (`DebtConsolidationPausedApplicationsTest`). | ☐ production |
| 3 | Disable the 4 DEMO lenders, the demo offer and both demo rule sets; nothing demo in matching or eligibility | Restore (`--hide-created`) plus `run.sh rulesets`; checked again by `verify` | ☐ production |
| 4 | Investigate the DC application of 26 Nov 20:52 UTC; prepare a correction | `39-…`: the customer was shown a DEMO "not eligible" result; no bureau, no lender. Draft message ready for approval. | ✅ investigated; ☐ message approval |
| 5 | Keep the 5 journeys on the duplicate product | Restore changes only content rows; `verify` checks them | ☐ production |
| 6 | No rebuild of the local database from the full export | Not done; the local database is untouched | ✅ |

## 7. Production action log

Fill in from `storage/app/private/incident-2026-11-26/actions.log` on the server; that log is the primary record. Rehearsal log: `evidence/2026-11-27-runbook-rehearsal/actions.log`.

| UTC time | Operator | Phase / action | Result | Evidence file |
| --- | --- | --- | --- | --- |
| | | pause-dc | | |
| | | preflight (database confirmed) | | |
| | | backup + integrity check; copied off the server to | | |
| | | preview + comparison | | |
| | | apply | | |
| | | rulesets | | |
| | | verify | | |
| | | smoke | | |

## 8. Production-data copies on this machine

The Owner said not to delete them until the restore is verified and the evidence is preserved. Then delete them securely and report.

| Copy | Location | Holds personal data | Delete after verification |
| --- | --- | --- | --- |
| Original export (102 files, 35 MB) | `~/snap/mysql-workbench-community/13/dumps/Dump20261127/` | Yes | ☐ |
| Imported rehearsal copy and its backups (private MySQL instance, stopped) | Claude's session scratchpad, `…/scratchpad/prodcopy/` (about 450 MB) | Yes | ☐ |
| Parsed `audit_logs` (JSON lines) | Claude's session scratchpad, `…/scratchpad/audit.jsonl` | Content only, no customer fields | ☐ |
| Evidence in the repo (`evidence/2026-11-27-*`) | `docs/debt-consolidation/evidence/` | No: checked for phone numbers, e-mail addresses and PAN-like strings | keep |
