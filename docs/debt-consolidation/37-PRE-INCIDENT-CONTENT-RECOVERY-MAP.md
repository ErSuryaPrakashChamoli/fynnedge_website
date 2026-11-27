# 37 — Pre-incident content recovery map

**Status:** recovery prepared, tested, and **rehearsed on a private copy of the production database** (export of 27 Nov 06:31 UTC; results in `38-…` §1). **Not yet applied to production**: that needs Owner approval and someone with production access.
Incident details are in `36-…`.

## 1. Recovery point

| Environment | Recovery point | Best source | Available here? |
| --- | --- | --- | --- |
| **Production** | State at **2026-11-26 13:05:42 UTC** (18:35:42 IST), one second before the **first** of the two `DatabaseSeeder` runs | **Production `audit_logs`**: every overwrite is logged with each changed column's exact old value | Yes, in the 27 Nov export; the restore itself runs on production |
| Production, tables not audited | Same | A production backup or snapshot taken before 13:05 UTC on 26 Nov, or production binlog point-in-time | No; ask the hosting provider |
| **Local** | State before 2026-11-26 11:29:30 IST | None exists (see §6) | No |

### Why `audit_logs` is reliable

`App\Models\Concerns\Auditable` writes one row per save, with `{column: {old, new}}` for every changed column:
- `old` is read in the `updated` event, before Eloquent syncs the originals, so it is the true pre-save value.
- The content models below exclude no columns, and nothing prunes `audit_logs`.
- Console writes have `user_id = NULL`; staff edits carry their user id. That is how the seeder's writes are told apart.

Covered models: `LoanProduct`, `LoanLandingPage`, `Page`, `Article`, `Faq`, `Testimonial`, `Lender`, `LenderProduct`, plus the eligibility models, which are reported but not restored automatically.

## 2. Classification

| Class | What | Treatment |
| --- | --- | --- |
| **A. Pre-existing website content** | The 464 URLs in `evidence/2026-11-26-incident-affected-urls.csv`: 11 products, 437 landing and location pages, About, 6 legal and careers pages, 10 articles. Also FAQs, testimonials, lenders and offers, calculator bodies and the other items in `36-…` "Impact". | Restore to the 13:05:42 UTC values. |
| **B. Debt Consolidation** | 7 DC pages, their FAQs, the DC setting, DC code, migrations, tests and docs | **Preserve.** No seeder touched them. The restore command refuses DC pages and their FAQs by slug, whatever the window. |
| **C. Shared records** | Settings (contact, business profile), navigation, homepage sections, page SEO overrides | **Nothing to restore.** The seeders write settings back unchanged or fill blanks only, and never write navigation, marketing sections or page SEO. |

## 3. Record-by-record comparison

The restore command's **preview prints the per-record table**: Record, Field, Now, Restores to. It also lists every field it will **not** restore and why. The preview run on the copy of the production database is saved as `evidence/2026-11-27-restore-rehearsal-preview.txt`: 819 fields across 480 records, and 39 created records to hide. Generate it again on production (step 5) and attach that output as the signed-off comparison.

| Record group | Count | Current (live) | Original, from production `audit_logs` |
| --- | --- | --- | --- |
| Loan products | 11 (#2–#12) | Starter summary and body; "Updated 26 Nov 2026" | Own text, e.g. gold loan: "use the gold you already have with you to get immediate finance …"; published 8 Sep |
| `personal-loans` (#1) | 1 | Untouched | — |
| Duplicate `personal-loan` (#14) | 1 | Published since 13:05:43 UTC; staff edit seen in the sitemap at 27 Nov 09:16 UTC (after the export) | Did not exist |
| Landing and location pages | 437 | Starter excerpt and body; 12 moved to #14 | Own text, product #1 for the 12 moved pages; published 8 Sep or 13 Oct |
| Pages created on #14 | 25 | Published | Did not exist |
| `/about` | 1 | Starter body, title "About FynnEdge" | Own body, title "About"; published 8 Sep |
| Legal and careers pages | 6 | Starter text, "Updated: November 26, 2026" | Own approved text; published 8 Sep |
| Articles | 10 | Own slugs, starter text, dated 26 Nov | Own text, published, dated 8 Sep |
| Testimonials | 6 | Published starter quotes | Drafts |
| FAQs | 5 overwritten, 8 created | Published | 4 were drafts, 1 published; 8 did not exist |
| Lenders and offers | 4 + 1 created, 4 changed | DEMO lenders active | DEMO rows did not exist |

## 4. Restoration plan (production)

**Approved by the Owner on 27 Nov 2026**, window `2026-11-26 13:05:00`–`13:09:59` UTC, with mandatory controls. These are scripted in `release/content-restore-2026-11-27/run.sh`, with `checks.php` beside it. The script was rehearsed end to end on the copy on 27 Nov, every phase passed, and the evidence is in `evidence/2026-11-27-runbook-rehearsal/`.

The operator runs it from the application root, one phase at a time: `OPERATOR="Full Name" docs/debt-consolidation/release/content-restore-2026-11-27/run.sh <phase>`. Each phase appends `UTC time | operator | host | phase | result` to `storage/app/private/incident-2026-11-26/actions.log` and keeps its output beside it.

| Order | Phase | Owner control it covers | Stops when |
| --- | --- | --- | --- |
| 1 | `pause-dc` | Pause new DC applications now. It switches off "Accept individuals" and "Accept businesses"; the service and pages stay on (no DC rollback). The same toggles are in admin → Debt Consolidation Settings. | DC pages not intact |
| 2 | `preflight` | Confirm the exact database: shows host, database, timezone and the export's fingerprint (991 / 517 / 474 audit rows), then asks the operator to type the database name. Saves a snapshot of customer-data row counts. | Fingerprint differs; name not confirmed |
| 3 | `backup` | Fresh backup immediately before the restore, then integrity check: gzip, completion trailer, table count, key tables, sha256. With `VERIFY_DB=<empty scratch db>` it also test-restores and compares row counts. Copy it off the server. | Any integrity check fails |
| 4 | `preview` | Run the preview for the approved window and compare it line by line with the rehearsal. Only differences explained by staff edits after the window are accepted. | Material difference: **stop and report to the Owner** |
| 5 | `apply` | Only the verified selective restore, `--apply --hide-created`. The operator confirms at the prompt. | No matching preview; no verified backup |
| 6 | `rulesets` | Archive demo rule sets #3 and #4, only if they are the 13:05 demo sets. Verifies no DEMO lender, offer or rule set is active. | Anything still active |
| 7 | `verify` | CMS records, publication states, personal loan relationships, DC pages and FAQs, DC application, journeys, no row lost; a second preview finds nothing left. | Any check fails |
| 8 | `smoke` | HTTP checks of the restored URLs, 404s, legal pages, DC pages, the paused notice and the sitemap (cache-busted). | Any probe fails |

Not part of this operation (Owner): no migrations, no seeders, no whole-database restore. The command needs only to be deployed. Its release has no migrations, and the entrypoint migrates only with `RUN_MIGRATIONS=true`.

The steps below are the reasoning behind each phase.

Each step is reversible until step 6, and step 6 writes its own backup first.

1. **Back up production now:** a full `mysqldump --single-transaction`, kept off the server. This preserves the current state, including the DC pages published at 13:22 UTC.
2. **Confirm the window.** On production, read-only, in the app's own session (IST); a client set to UTC shows 07:35 and 07:39 instead:
   ```sql
   SELECT DATE_FORMAT(created_at, '%H:%i') AS minute, COUNT(*), MIN(created_at), MAX(created_at)
   FROM audit_logs
   WHERE user_id IS NULL AND created_at BETWEEN '2026-11-26 12:30:00' AND '2026-11-26 13:30:00'
   GROUP BY minute ORDER BY minute;
   ```
   Expect exactly what the 27 Nov export shows:
   - 13:00 (DC migrations, 33 rows; DC is protected).
   - **13:05:43–13:05:49 (517 rows) and 13:09:07–13:09:12 (474 rows): the two runs.**
   - 13:22 (DC publish, 32 rows).

   If anything else appears between 13:05 and 13:10, stop and review it.
3. **Rehearse on a copy.** ✅ Done on 27 Nov with the 06:31 UTC export, on a private MySQL 8.4 instance at +05:30, separate from the working `fynnedge` database. Results in `38-…` §1: everything restored as expected, and a second preview finds nothing left. Repeat only if a newer export is taken.
4. **Release the code.** The command and the seeder guard are code-only, with no migrations. They must be committed and deployed through the normal runbook (`31-…`), which is the Owner's decision. Until then, the restore can only be run on a copy.
5. **Preview on production. The window must cover both runs:**
   `php artisan content:restore-from-audit-log --from="2026-11-26 13:05:00" --to="2026-11-26 13:09:59"`
   - A window that starts at 13:09 misses the first run. It would put 446 `published_at` values back to the first run's 13:05 timestamps, and leave the duplicate product, its 25 pages, 2 DEMO lenders and 8 FAQs live.
   - The command keeps the first `old` and last `new` of every field in the window. That is tested with two runs (`RestoreContentFromAuditLogCommandTest`).

   Save the output: it is the record-by-record comparison for §3.
6. **Apply:** add `--apply --hide-created` and confirm.
   - The command writes `storage/app/private/content-restores/before-restore-<time>.json` first.
   - It restores each field whose live value is still exactly what the seeder wrote.
   - It sets content the seeder created to Draft (lenders and offers to Inactive).
   - It keeps anything staff edited since, and lists it.
   - Machine translation is paused during the restore, and the content cache is invalidated afterwards.

   **Expected on production:**
   - About 480 records and 819 fields restored, and 39 created records hidden (the rehearsal figures).
   - A few fewer where staff edited after the 06:31 UTC export, each listed as kept. These include the duplicate `personal-loan` product (staff edit 27 Nov 09:16 UTC), which stays live for the Owner to decide, and the article edited 27 Nov 08:57 UTC (not part of the incident).
7. **Handle what the command does not restore** (exact list from the 27 Nov export):

   | Item | Action |
   | --- | --- |
   | Eligibility rule set #4 (on SBI home loan offer #7) and #3 (on DEMO offer #359), with 12 rules and 12 conditions | Deactivate both rule sets in the admin. They are audited but outside the command. |
   | Job opening #8 "Digital Marketing Manager" (published on `/careers`) | Close it, unless it is a real vacancy. |
   | 4 calculator page bodies (FD, SIP, daily SIP, GST: `calculator_pages` #1–#4) | Load a backup taken before 13:05 UTC into a separate database and copy the fields back; without one, re-enter by hand. |
   | Flexi product SEO title and description (`seo_metas` #1) | As above. |
   | Document types #6 and #7 | As above. |
   | 358 lender document requirements (created; none existed before for those offers) | Owner: keep as the checklist, or remove. |
   | Journey definition #13 with 6 steps and 20 fields (duplicate product #14) | Keep: 5 customer journeys use it. Decide together with product #14. |
   | 6 employer categories, 2 employer ratings, 4 credit-score page rows, 5 newsletter segments and 1 template, 6 permissions | Owner: keep or remove. They are new rows; nothing earlier was overwritten. |
   | Marketing and SEO role permissions (`syncPermissions`) | Check in admin → Roles; earlier additions are not recorded anywhere. |
   | Business description and locality settings | None: they were blank and are now correct. |
8. **Verify** using the checklist in `38-…` §1.

## 5. Records intentionally preserved

| What | Why |
| --- | --- |
| All DC pages, DC FAQs, the DC setting, DC code, migrations, tests and docs | Class B; the command refuses DC content; no code or data of it was changed. |
| Any field edited by staff after the window | The command restores a field only if its live value equals what the seeder wrote. |
| The duplicate `personal-loan` product | Created by the seeder but edited by staff on 27 Nov; Owner decision. |
| The article edited 27 Nov 08:57 UTC (`can-i-avail-a-personal-loan-with-a-low-cibil-score`) | Not part of the incident. |
| Banners, navigation, marketing sections, promo bars, page SEO, settings | Not written by the seeders. |

## 6. Restored so far and unrecoverable

**Restored so far:** nothing on production. The full restore was applied to a private copy of the 27 Nov export only, as a rehearsal (`38-…` §1). The local `fynnedge` database was not touched, and it has nothing to restore from (below).

**Unrecoverable locally (checked and confirmed):** all local rows that existed before 26 Nov 11:29:30 IST. That covers:
- local-only pages, banners, marketing sections, navigation, page SEO, testimonials and settings;
- users and roles; local enquiries and customers; test credit reports.

Sources checked: `~/snap/mysql-workbench-community/13/dumps` (LMS only), all `*.sql*`, `*.dump`, `*.zip` under the home folder (other projects and the Flutter app), `/var/backups`, `/opt`, the 26 Nov `/tmp` dump (empty database, now gone), the Wayback Machine (no 2026 copies), and the local `audit_logs` (dropped with the tables). The local MySQL binlog needs root and holds at most 30 days of row changes, with no rows for data untouched in that period; it was not attempted.

**Practical local recovery:** after production is restored, import a production export into local, as originally recommended. Then run the local-only migrations, which apply the DC schema; the DC pages come from migrations, so DC is rebuilt intact. Finally re-apply the local DC state (service on, pages published). Take a `mysqldump` first. Today's local snapshot is in `~/fynnedge-backups/2026-11-27-content-incident/`.

**Unrecoverable on production unless a backup exists:** the non-audited items in step 7.
