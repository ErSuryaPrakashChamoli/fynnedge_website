# 36 — Content publishing incident report (26 Nov 2026)

**Status:** investigated, root cause fixed in code, restore rehearsed on a copy of the production database (`38-…` §1). **Production content not yet restored**: that needs Owner approval (plan in `37-…`).
**Prepared:** 27 Nov 2026; updated the same day from the production database export. All times are given in IST and UTC. The app, its logs and `audit_logs` use UTC.

> **Reading production timestamps.** The production database session runs at IST (+05:30) while the app runs in UTC. A `mysqldump` (which uses `+00:00`) therefore shows every timestamp 5 h 30 min earlier than the app does: dump `07:39:08` is app `13:09:08 UTC`. All times below are app UTC.

## Summary

Two separate events on 26 Nov 2026 have been reported as one incident.

| | Event 1: local database wiped | Event 2: production content overwritten |
| --- | --- | --- |
| **When** | 11:29:30 IST (05:59:30 UTC) | **Two runs: 18:35:43–18:35:49 and 18:39:07–18:39:12 IST (13:05:43–13:05:49 and 13:09:07–13:09:12 UTC)** |
| **Where** | Local development MySQL `fynnedge` | **Production (fynnedge.com)** |
| **What ran** | `php artisan test … DebtConsolidationApplicationFormTest.php`, run by Claude | A bare `php artisan db:seed --force`, i.e. the default `DatabaseSeeder`, not a targeted `--class`, **run twice** |
| **Who ran it** | Claude (session `65c2bce6`) | Someone with production shell access. **Not Claude**: no session has production access, and Claude's only commands that minute were `git ls-remote` and read-only `curl` GETs (13:08:43–13:09:22 UTC). Confirm the operator from the server's shell history. |
| **Effect** | Every local table dropped and recreated, all rows lost. At the Owner's request (11:38 IST) the database was then rebuilt from the default seeders, so the local site now shows starter content. | 11 live loan products, 437 landing and location pages, About, legal and careers pages, 10 articles, 6 testimonials and 5 FAQs were replaced with starter text and re-dated. A duplicate "Personal Loan" product, 25 pages on it, 4 "DEMO Lender" rows and 8 FAQs were created and published. Full list under "Impact on production". |

Neither event was caused by the Debt Consolidation publish itself. `PublishDebtConsolidationPagesSeeder` (commit `79a9324`, branch `production`) touches only the 7 Debt Consolidation (DC) pages and their FAQs, and its test proves that. It was also written at 18:46–18:49 IST, **7 minutes after** the production overwrite.

## Timeline (26 Nov 2026 unless stated)

| IST | UTC | Event | Evidence |
| --- | --- | --- | --- |
| ~11:15 | ~05:45 | `php artisan optimize` creates `bootstrap/cache/config.php`. It was not run by Claude (no such command in any session); a hand-typed `migrage` is logged at 11:16. | file mtime; `laravel.log` 05:46:02 |
| 11:20 | 05:50 | Owner: DC pages and calculator not showing (local). | session transcript |
| 11:23 | 05:53 | Claude publishes the 7 DC pages and 25 FAQs **locally**. | transcript |
| **11:29:30** | **05:59:30** | Claude runs one DC test file. The cached config overrides `phpunit.xml`, so `RefreshDatabase` runs `migrate:fresh` on local MySQL. Tables are recreated at 11:29:32–11:29:47. | transcript; `information_schema.tables.create_time`; `laravel.log` 05:59:42 (env `local`, DB `fynnedge`) |
| 11:31–11:33 | 06:01–06:03 | Claude finds no FynnEdge backup. It adds a guard to `tests/TestCase.php` (tests refuse any database but in-memory SQLite) and clears the config cache. | transcript |
| 11:36 | 06:06 | Owner picks "Import a production export". | transcript |
| 11:38:22 | 06:08:22 | Owner: "revert it and use database seed instand". | transcript |
| 11:38:47 | 06:08:47 | `php artisan db:seed --no-interaction` on the **empty local** database: starter content (33 lenders, 12 products, 15 pages, 10 articles, 52 FAQs). | transcript; table counts |
| 11:39:28 | 06:09:28 | DC switched on and its pages published **locally**. | transcript; `pages.updated_at` |
| 17:49 | 12:19 | Local commit `bb33a97`. | git |
| before 18:30 | before 13:00 | `bb33a97` pushed to `origin/production` and deployed. | git refs |
| 18:30:13–15 | 13:00:13–15 | DC migrations run on production (batch 37): DC permissions and roles; the 7 DC pages and 25 DC FAQs created as drafts. | `migrations`, `permissions`, `audit_logs` |
| 18:31–18:35 | 13:01–13:05 | Site unreachable: the uptime monitor's pings stop. | `analytics_events` |
| **18:35:43–18:35:49** | **13:05:43–13:05:49** | **First `DatabaseSeeder` run** (517 audited writes). It creates the duplicate product `personal-loan` (#14) and everything listed under "Impact". | `audit_logs` ids 3739–4255 |
| 18:36:52 | 13:06:52 | DC settings saved; DC service switched on. | `settings` |
| 18:38:30 | 13:08:30 | Owner: "why top consolidation not show … on production". | transcript |
| 18:39:03 | 13:09:03 | `/debt-consolidation` returns 404 (pages still drafts). | `not_found_urls` |
| **18:39:07–18:39:12** | **13:09:07–13:09:12** | **Second `DatabaseSeeder` run** (474 audited writes). It rewrites the same content and dates again. | `audit_logs` ids 4256–4729; sitemap `lastmod` |
| 18:43:13 | 13:13:13 | Owner publishes the DC pages in the admin (the only staff content edit after the runs, up to the export). | `audit_logs` (user 1) |
| 18:46–18:49 | 13:16–13:19 | Claude writes `PublishDebtConsolidationPagesSeeder` (`79a9324`, local `production` branch, not pushed by Claude). | git; transcript |
| 18:52 | 13:22 | The 7 DC pages are saved on production (`79a9324` is now on `origin/production`). | sitemap `lastmod` |
| 27 Nov 02:22 | 26 Nov 20:52 | A customer verifies a DC one-time code and creates a debt consolidation application on production, with consent recorded. | `audit_logs`, `mobile_otp_challenges` |
| 27 Nov 12:01 | 06:31 | Production database exported (102 tables) to this machine. | `~/snap/mysql-workbench-community/13/dumps/Dump20261127` |
| 27 Nov 12:18 | 06:48 | The machine reboots. `/tmp` is cleared, including the 26 Nov dump of the (already empty) local database. | `uptime -s` |
| 27 Nov 14:27 / 14:46 | 08:57 / 09:16 | Staff edit an unrelated article and the **duplicate** `/loans/personal-loan` product on production. | sitemap `lastmod` |

### How the production run is identified

Today's production `sitemap.xml` has 549 URLs, and **464 carry `lastmod` 2026-11-26 between 13:09:07 and 13:09:12 UTC**. Their order matches `DatabaseSeeder`'s call order exactly:

- 13:09:07: `/about` and `/loans/personal-loan`, written by `DatabaseSeeder::run()` itself.
- 13:09:08: loan products (`CalculatorLoanProductSeeder`, `JourneySeeder`).
- 13:09:08–11: 435 landing and location pages (`LoanLandingPageSeeder`, `LoanLocationPageSeeder`).
- 13:09:12: exactly `LegalPageSeeder`'s 6 slugs and `ArticleSeeder`'s 10 slugs.

The live text now equals the seeder text: the About body and the summaries of the gold, home, car, business, personal and mudra products were checked word for word. The full URL list is in `evidence/2026-11-26-incident-affected-urls.csv`.

**Production's `audit_logs` (export of 27 Nov) confirms this and shows a first run the sitemap could not show,** because the second run overwrote the same `lastmod` values:
- 517 console writes (`user_id` NULL) at 13:05:43–13:05:49 UTC.
- 474 at 13:09:07–13:09:12 UTC.
- No other console writes between 13:00:16 and 13:22:31.

On 446 records the second run rewrote `published_at` again. The value from before the incident is therefore the `old` of the **first** run; the second run's `old` is the first run's timestamp.

## Root cause

1. **Production:** `DatabaseSeeder` is a development bootstrap. Nearly every seeder it calls uses `updateOrCreate`, which overwrites matching rows with starter text and sets `status = Published, published_at = now()`. Nothing stopped it from running on a populated or production database: Laravel's production prompt is skipped by `--force`. The project rule "production only runs migrations" (`.ai/rules/faqs.md`) existed but was not enforced in code. No document told anyone to run a bare `db:seed`; every runbook uses `--class`.
2. **Local:** a cached config (`php artisan optimize`) makes Laravel ignore `phpunit.xml`, so a test run used the real MySQL database. This was fixed on 26 Nov by the `TestCase` guard, which is still in place. Note: `bootstrap/cache/config.php` **exists again today** (mtime 26 Nov 12:52), so the guard is what protects the database now.
3. **Slug mismatch, which made it worse:** production's product is `personal-loans`; the seeder's is `personal-loan`. The seeder therefore created a second "Personal Loan" product and moved existing landing pages (matched by slug) onto it. The originals now 404: `/loans/personal-loans/personal-loan-for-travel`, `…-for-self-employed` and `…-for-debt-consolidation` were checked.

## Impact on production

**Confirmed from the production database** (export of 27 Nov, 06:31 UTC; every table compared with the two run windows):

| Content | Effect | Restored by the command? |
| --- | --- | --- |
| 11 loan products (#2–#12) | Summary, body, features, eligibility, documents, process steps, amounts, tenure and rates replaced with starter values; published date reset ("Updated 26 Nov 2026"). | Yes |
| `personal-loans` (#1), the real personal loan product | **Untouched.** | — |
| Duplicate `personal-loan` (#14) | Created and published, with 25 new pages, a DEMO lender offer (#359) and journey definition #13. Since then 5 customer journeys ran on it and 1 eligibility result was produced against the DEMO offer or rules. | Hidden (draft); customer data kept |
| 437 landing and location pages | Title, excerpt, body, sort order, product link and date rewritten. 12 of them (1/3/5/10 lakh, salaried, self-employed, women, senior citizens, medical, travel, home renovation, debt consolidation) were moved from #1 to #14, and their old URLs 404. `not_found_urls` has logged 11 of the 12 since, mostly Google. | Yes |
| `/about`, privacy policy, terms, disclaimer, grievance, careers, credit-report terms | Starter text, shown as updated 26 Nov 2026. **Compliance risk:** approved legal wording is not live. | Yes |
| 10 articles (#1–#10, `how-emis-work` …) | They existed and were published before. Content rewritten and dates reset to 26 Nov, so they top the newest-first lists. | Yes (original dates back) |
| 6 testimonials (#1–#6) | Were **drafts** (staff had replaced them with #7–#18); re-published with starter text. | Yes (back to draft) |
| FAQs | 5 overwritten and re-published (#4, #12, #13, #14, #20; four of them were drafts). 8 created and published (#158–#165), 6 of them at the top of the general list. | Yes; created ones hidden |
| Lenders and offers | 4 "DEMO Lender" rows created active (#50–#53), two per run. DEMO offer #359 created. Flexi-hybrid offers #141, #143, #144 and #317 changed. | Yes; DEMO rows set inactive |
| Eligibility | 2 rule sets with 12 rules and 12 conditions created: on DEMO offer #359, and on offer #7 (State Bank of India home loan, inactive). | **No** (audited, outside the command): deactivate by hand |
| Not audited, overwritten | Calculator page bodies #1–#4 (FD, SIP, daily SIP, GST); flexi product SEO title and description (`seo_metas` #1); document types #6 and #7. | **No**: earlier values need a backup from before 13:05 UTC |
| Not audited, created | 358 lender document requirements (none existed before for those offers), job opening #8 "Digital Marketing Manager" (published), journey definition #13 with 6 steps and 20 fields, 6 employer categories, 2 employer ratings, 4 credit-score page rows, 5 newsletter segments and 1 template, 6 permissions. Marketing and SEO role permissions re-synced. | **No**: Owner decides keep or remove |
| Settings | Business description and locality filled (they were blank). | Not needed |

**Not affected** (no row written in either run): the admin users, banners, marketing sections, navigation (empty), promo bars, page SEO overrides, redirects, social videos, video testimonials, customers, leads, enquiries, credit-bureau data, and DC pages, FAQs and settings.

**Nothing was deleted.** All changes are overwrites or new rows; no seeder deletes, and the audit log has no delete in either run.

**Separate finding: the DC service is taking real applications.** At 20:52 UTC on 26 Nov a customer verified a DC one-time code and created a debt consolidation application, with consent recorded. `00-project-status.md` says live customer applications are not authorised.

**Local:** every row was lost at 11:29 IST, then replaced by starter content plus the DC state. There is no local source for the pre-wipe data (see `37-…`).

## Evidence sources checked

| Source | Result |
| --- | --- |
| Git history, branches, diffs | `79a9324` (scoped DC publish) only on `production`; no commit runs or changes `DatabaseSeeder` behaviour. |
| Claude session transcripts (all sessions since 25 Nov) | Exact commands, times and the Owner's instructions for Event 1; no production access in any session. |
| `storage/logs/laravel.log` | Wipe at 05:59:42 UTC under env `local`; `migrage` typo at 05:46. |
| Local DB `information_schema`, row counts, `audit_logs` | All tables created 11:29:32–47; `audit_logs` begins after the wipe. |
| Production public pages and `sitemap.xml` (read-only GETs) | Event 2 timing, scope and live text. |
| Local dumps: `~/snap/mysql-workbench-community/…/dumps`, `~/Downloads`, `~/development`, `*.sql*` | Only LMS (`fynn_lms_prod`), other projects, or the Flutter app; no FynnEdge website database. |
| `/tmp` dump taken 26 Nov 11:38 | Lost in the 27 Nov reboot; it held the already-empty database. |
| MySQL binary log (local) | ON, ROW/FULL, 30-day retention; needs MySQL root. It cannot restore rows untouched in 30 days, because `DROP TABLE` has no row images. |
| Wayback Machine | No 2026 snapshots of the affected pages. |
| Docker entrypoint, runbooks, `.ai/rules`, `composer.json` | No step runs `db:seed`; all seeding instructions use `--class`. |
| Production database export (27 Nov, 06:31 UTC, 102 tables) | Received. `audit_logs` pins both runs; every table compared with the run windows; restore rehearsed on a private copy (`38-…` §1). |
| Production backups, binlog, shell history | **Not accessible from here.** Needed for the non-audited items and to name the operator. |

## Fixes made (local working tree, not committed)

1. `DatabaseSeeder` refuses to run when the database already holds website content (any row in `loan_products`, `loan_landing_pages`, `articles`, `lenders`, `testimonials` or `job_openings`):
   - It first lists the existing row counts.
   - On production it always refuses, even with `--force`.
   - Elsewhere it runs only after an explicit "yes" at the console; non-interactive runs refuse.
   - A freshly migrated database still seeds without asking.
2. New `php artisan content:restore-from-audit-log --from --to [--apply] [--hide-created]`. It reverts unattended changes field by field from `audit_logs`, keeps anything edited since, never touches DC content, previews by default, asks for confirmation, and writes a JSON backup before changing anything.
3. Project rule recorded in `.ai/rules/seeders.md`.

Bulk publishing that needs a preview, confirmation and backup is covered by these two controls:
- The only bulk content writers are seeders. `DatabaseSeeder` is now guarded, and `PublishDebtConsolidationPagesSeeder` touches only DC pages and FAQs.
- The restore command previews, confirms and backs up before writing.

Targeted seeders run with `--class` are still deliberate operator actions; see the outstanding items in `38-…`.
