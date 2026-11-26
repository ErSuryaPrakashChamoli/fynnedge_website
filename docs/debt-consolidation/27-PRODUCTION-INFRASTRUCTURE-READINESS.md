# 27 — Production infrastructure readiness

**For:** the Owner and whoever operates the production host (Operations).
**Date:** 26 Nov 2026.

**Basis.** This report is based on the deployment files in the repository:
- `Dockerfile`, `docker-compose.yml`;
- `docker/entrypoint.sh`, `docker/nginx/nginx.conf`, `docker/php/php.ini`, `docker/supervisor/supervisord.conf`;
- `.env.example`, `routes/console.php`.

**Engineering has no access to production.** It has not seen the production host, its `.env`, its database or its proxy. Every production fact below is therefore marked **"to confirm"** where it cannot be read from the repository. Nothing in production was read or changed.

## 1. Summary

| Item | Status |
| --- | --- |
| PR-01 Full regression | **COMPLETE** (run 7, `20-…`) |
| PR-02 Production mail and delivery test | **BLOCKED**: needs production access (Operations) |
| PR-03 Scheduler running and monitored | **BLOCKED**: not in the image; must be confirmed on the host (Operations) |
| PR-04 Persistent, backed-up private storage | **BLOCKED**: compose has a volume; backup to be confirmed (Operations) |
| PR-05 Pre-deployment backup (DB, files, `APP_KEY`) | **BLOCKED**: taken at deployment time (Operations) |
| Docker migration risk | Analysed (§4). A proposed fix is ready as a patch, **not applied** (Owner decision INF-01). |
| Docker runtime PHP version | **Blocker, INF-02**: the PHP 8.3 runtime cannot run the locked dependencies (≥ 8.4.1). Pre-existing. A patch is prepared, **not applied**. |
| Migration upgrade rehearsal | **Done** on a scratch database built from the clean baseline (§4.3). It found one rollback defect, which is now **fixed and tested**. |

## 2. Architecture as found in the repository

| Component | Configuration | Notes for Debt Consolidation (DC) |
| --- | --- | --- |
| Image | Multi-stage: Node 22 builds assets; Composer 2 installs `--no-dev`; runtime `php:8.3-fpm-alpine` with nginx and supervisor | **Blocker, INF-02.** `composer.json` allows `^8.3`, but the locked dependencies (Symfony 8 components) require PHP ≥ 8.4.1. Composer's generated `vendor/composer/platform_check.php` stops every request on PHP 8.3. This predates DC: `Dockerfile` and `composer.lock` are unchanged since before the project. Either production is not built from this Dockerfile as committed (**to confirm**), or the runtime must move to PHP 8.4+ (patch prepared, §6). |
| PHP extensions | pdo_mysql, mbstring, exif, pcntl, bcmath, gd, zip, intl, curl, opcache, redis | DC needs: bcmath (not used; money is in integer paise), intl and mbstring (formatting), gd (dompdf images). All present. |
| PHP limits | `memory_limit 256M`, uploads 25 MB, `max_execution_time 120`, opcache without timestamp validation | DC uploads are limited to 10 MB in the app. PDF reports render well within the limits. The opcache setting needs php-fpm restarted on deploy (a new container does this). |
| Database | MySQL 8.4 (compose service; production host to confirm) | 18 new `debt_*` tables; InnoDB; foreign keys |
| Cache / session / queue | `database` by default (compose); Redis service present but optional | DC uses cache locks (duplicate-case guard). The `database` store supports them. |
| Queue worker | supervisor `queue:work --sleep=3 --tries=3 --max-time=3600` as `www-data` | DC notifications are queued. The customer notification is encrypted (`26-…` L2). |
| Scheduler | **Not in the image** | PR-03. DC adds `debt-consolidation:enforce-retention` daily at 03:00. Other site jobs (newsletter every minute, bureau jobs) depend on it too. |
| Storage | Named volume `storage_data` mounted on `storage/app`. The entrypoint re-creates `storage/app/private` and chowns it to `www-data`. | DC documents and reports are written encrypted to `storage/app/private/debt-consolidation/`. The volume must be backed up (PR-04). |
| Web server | nginx on port 80, `server_name _`, `client_max_body_size 25m`, `X-Frame-Options`, `nosniff`, dotfiles denied | **HTTPS terminates upstream** (to confirm). The host is not restricted: see INF-04. |
| Mail | `.env.example` has `MAIL_MAILER=log` | Production transport to confirm (PR-02) |
| SMS | Areach (`AREACH_*`) | DC uses the existing OTP service only. DLT templates: OP-01 decision. |
| Logs | stdout/stderr through supervisor; Laravel `stack` → `single` | Collect from the container runtime. Production `LOG_LEVEL` should be `warning` or `error` (to confirm). |
| Monitoring / error reporting | None in the repository | **INF-06**: no uptime or error alerting found. Recommend at least uptime checks on `/` and `/up`, and alerting on failed jobs. |
| Backups / restore | None in the repository | PR-04 and PR-05; procedure in `32-…` |
| Failed jobs | `failed_jobs` table (Laravel default) | Watch it after go-live (`25-…` SOP; `34-…`) |

## 3. Production configuration checklist

Operations confirms each item and fills in the evidence. No value is shown here, because engineering has none.

| # | Setting | Required for DC | Confirmed by / evidence |
| --- | --- | --- | --- |
| C-01 | `APP_ENV=production`, `APP_DEBUG=false` | No stack traces shown to customers | |
| C-02 | `APP_URL=https://fynnedge.com` (exact public origin) | DC email links are built from it (`26-…` M2) | |
| C-03 | `APP_KEY` present and **backed up separately** | Every DC document and report is encrypted with it | |
| C-04 | Working `MAIL_*` transport and `MAIL_FROM_ADDRESS` on the FynnEdge domain | Customer and team emails (PR-02) | |
| C-05 | `QUEUE_CONNECTION=database` (or redis) with the worker running | DC notifications | |
| C-06 | Cache store supports locks (`database` or `redis`) | Duplicate-case guard | |
| C-07 | `SESSION_SECURE_COOKIE=true` behind HTTPS | Portal session cookies | |
| C-08 | `SESSION_ENCRYPT=true` (**INF-05**, Owner decision; signs everyone out once) | Calculator inputs in sessions (`26-…` L2) | |
| C-09 | `schedule:run` every minute on the host or in a sidecar (PR-03) | Retention job; site jobs | |
| C-10 | `storage/app` on a persistent volume in the backup set (PR-04) | Documents and reports | |
| C-11 | Optional: `DEBT_CONSOLIDATION_DRAFT_RETENTION_DAYS` (180), `DEBT_CONSOLIDATION_CALCULATOR_RETENTION_DAYS` (90) | Retention (Legal may change these, OD8) | |
| C-12 | Trusted proxy configured so HTTPS is detected behind the load balancer (to confirm how the existing site does this) | Secure URLs and cookies | |

## 4. Docker migration risk

### 4.1 The risk

`docker/entrypoint.sh` runs `php artisan migrate --force` on **every container start**, then caches config, routes and views. As a result:
- **Deploying the image is migrating.** There is no separate, observable migration step.
- **Any restart migrates.** That includes a crash loop, a host reboot, a scale-out or an orchestrator reschedule. If an image containing unapplied migrations is started by accident, for example during a test of the new image against the production database, the schema changes without anyone deciding it.
- **Two containers starting at once can race** on the same migration (no `--isolated`).
- **A failed migration leaves the container in a restart loop**, retrying the failed migration on each start.

### 4.2 Exactly what would run

Relative to `origin/main` (`084825b`, which is assumed to be what production runs; **to confirm** with `php artisan migrate:status` on production, read-only), these 8 migrations are pending, in this order:

| # | Migration | Kind | What it does | Additive? | `down()` |
| --- | --- | --- | --- | --- | --- |
| 1 | `2026_11_25_144700_create_debt_applications_tables` | DDL | Creates `debt_applications`, `debt_financial_profiles`, `debt_liabilities`, `debt_collaterals` | Yes, new tables only | Drops them |
| 2 | `2026_11_25_144702_create_debt_records_tables` | DDL | `debt_documents`, `debt_consents`, `debt_notes`, `debt_status_changes` | Yes | Drops them |
| 3 | `2026_11_25_144703_create_debt_calculation_tables` | DDL | `debt_scoring_configs`, `debt_calculation_snapshots`, `debt_scenarios`, `debt_foreclosure_quotations`, `debt_reports` | Yes | Drops them |
| 4 | `2026_11_25_144705_create_debt_lender_tables` | DDL | `debt_lender_policies`, `debt_lender_matches`, `debt_lender_applications` | Yes | Drops them |
| 5 | `2026_11_25_144706_create_debt_closure_tables` | DDL | `debt_closures`, `debt_closure_payments`; adds a foreign key on the **new** `debt_documents` table | Yes | Drops the FK and the tables (by FK name: works on MySQL, not on SQLite) |
| 6 | `2026_11_25_145413_seed_debt_consolidation_reference_data` | Data | Inserts 8 document types (an existing key is skipped), scoring configuration v1 (individual and business), the "Debt Advisor" role and the Debt permissions | Yes, inserts only | Deletes only the document types nothing uses. Role and permissions stay (harmless). |
| 7 | `2026_11_25_150300_create_debt_consolidation_pages` | Data | 7 **draft** pages with SEO metadata and 25 draft FAQs | Yes | Deletes those pages only while still draft and never edited |
| 8 | `2026_11_26_072745_rename_debt_consolidation_calculator_page_to_debt_analyser` | Data | Renames the draft calculator page to "Debt Consolidation Analyser", only where the shipped wording is unchanged | Yes | Reverses only what it wrote |

- **No existing table is altered or dropped.**
- **No existing row is changed.**
- **Backward compatibility.** The previous image keeps working on the migrated schema: it ignores the new tables and rows. The only visible traces would be:
  - 8 new document types in the shared document-type list;
  - the "Debt Advisor" role in the role list.
  Neither affects existing journeys.

### 4.3 Upgrade rehearsal (done)

Scratch SQLite database; never the local or production MySQL.

1. Migrated the database with the **clean baseline** code (`084825b`).
2. Switched to the release candidate. `migrate:status` listed exactly the 8 migrations above as pending.
3. Ran `migrate`: success.
   - 18 `debt_*` tables created;
   - all DC pages draft;
   - the calculator page renamed.
4. Ran `migrate:rollback`. The 3 data migrations rolled back.
   - **First rehearsal:** one draft page (the calculator page) survived the rollback. Migration 8 saved the page with a new `updated_at`, and migration 7's `down()` treats that as "edited by an editor".
   - **Fixed:** migration 8 now saves without touching timestamps. Test: *leaves the renamed page looking unedited, so a full rollback still removes it* (fails before the fix).
   - **Re-run after the fix:** every DC page was removed.
5. Migration 5's `down()` cannot run on SQLite, which cannot drop a foreign key by name. **On MySQL it works:** the full migrate → rollback → re-migrate cycle of migrations 1–7 was verified on MySQL at acceptance (`16-…`). Migration 8 adds no DDL.
6. Ran `migrate` again: success.

**Still to do on MySQL (runbook step, Operations):** run `php artisan migrate --pretend` against a **copy** of the production database to see the exact SQL before deploying.

The local MySQL user can only use the developer's own database, so engineering did not rehearse on MySQL in this stage. It did not touch the developer's local database either.

### 4.4 Controlled migration procedure (recommended)

Recommendation, **INF-01 (Owner decision)**: apply `release/entrypoint-explicit-migrations.patch`. With it:
- A start **without** `RUN_MIGRATIONS=true` never migrates. If migrations are pending, it **refuses to start** and lists them, so new code never runs on an old schema.
- The release runs the migration **once**, as an explicit and observable step: a one-off container with `RUN_MIGRATIONS=true`, or `docker compose run --rm -e RUN_MIGRATIONS=true app true`, followed by a normal start.
- `--isolated` prevents two migrators racing.
- Note for a fresh, empty database: the migrations table does not exist yet, so the pending check finds nothing. Start with `RUN_MIGRATIONS=true` the first time.

The patch applies cleanly (`git apply --check`). It is **not applied**, because it changes how every FynnEdge deployment behaves, not only DC.

**If the Owner prefers to keep today's entrypoint**, the runbook (`31-…`) still makes migration explicit:
1. Backup (PR-05).
2. `migrate --pretend` on a copy.
3. Deploy in a window.
4. Watch the container log for the 8 `DONE` lines.
5. Confirm with `migrate:status`.

The residual risk is that any later restart of a not-yet-approved image would migrate. Never start the new image against production until DP-01.

### 4.5 Rollback implications

See `32-ROLLBACK-AND-RECOVERY-PLAN.md`.
- **Instant rollback:** switch the service off. No deployment is needed.
- **Full rollback:**
  1. With the **new** image still running, run `php artisan migrate:rollback` (this rolls back the last batch; check it with `--pretend` first, and the list must be exactly the 8 above).
  2. Then deploy the previous image.
- **The order matters.** The old image does not contain these migrations, so it cannot roll them back, and the current entrypoint would not re-apply them.

## 5. PR-01 to PR-05: verification procedures

| ID | What | How to verify (production, by Operations, after authorisation) | Evidence to record | Status |
| --- | --- | --- | --- | --- |
| PR-01 | Full regression suite | Done by engineering: run 7 on the release candidate, with failures compared against the clean baseline | `20-REGRESSION-CLOSURE-REPORT.md` | **COMPLETE** |
| PR-02 | Mail | `php artisan tinker --execute 'Mail::raw("FynnEdge mail test", fn ($m) => $m->to("<ops mailbox>")->subject("Mail test"));'` on production, then confirm receipt **and** that the From domain passes SPF/DKIM | Screenshot of the received mail with headers; date | **BLOCKED** (Operations) |
| PR-03 | Scheduler | The host crontab or sidecar shows `* * * * * php artisan schedule:run`. `php artisan schedule:list` lists `debt-consolidation:enforce-retention`. The next morning, the log shows the 03:00 run. | `crontab -l` output; `schedule:list` output; log line | **BLOCKED** (Operations) |
| PR-04 | Private storage | The volume for `storage/app` is persistent (survives `docker compose down` / `up`) and is in the nightly backup. A test restore of one file works. | Volume name; backup job name; restore test date | **BLOCKED** (Operations) |
| PR-05 | Pre-deployment backup | Immediately before deploying: `mysqldump` of the production database; archive of `storage/app/private`; `APP_KEY` stored separately. Restore the dump to a scratch database and check the row counts. | File names, sizes, checksums, restore check | **BLOCKED** (Operations, at deployment) |

## 6. Other infrastructure findings

| ID | Finding | Recommendation | Owner | Status |
| --- | --- | --- | --- | --- |
| INF-01 | Migrations run on every start | Apply the explicit-migration patch (§4.4) | Owner | PENDING APPROVAL |
| INF-02 | **Blocker.** The runtime image is PHP 8.3, but the locked dependencies need PHP ≥ 8.4.1, so an image built from the committed Dockerfile cannot serve requests. This predates DC. | First confirm how production is actually built today. Then either apply `release/dockerfile-php-runtime.patch` (runtime → `php:8.5-fpm-alpine`, the version the tests run on; 8.4 would also work) or record the real production build. Build on a staging host and run `33-…` there before production. No container engine is installed on the development machine, so engineering could not build the image. | Owner / Operations | **BLOCKED** (confirmation and decision) |
| INF-03 | The scheduler is not part of the image | Add a `schedule:work` supervisor program, or a host cron (PR-03) | Operations | PENDING |
| INF-04 | nginx accepts any host (`server_name _`); Laravel does not restrict hosts | Set `server_name fynnedge.com www.fynnedge.com;` and add a default server that returns 444. DC emails are already safe (`26-…` M2). This protects the rest of the site. | Owner / Operations | PENDING APPROVAL |
| INF-05 | Sessions are not encrypted | `SESSION_ENCRYPT=true` | Owner | PENDING APPROVAL |
| INF-06 | No monitoring or alerting in the repository | Uptime checks on `/up`; alert on failed jobs and on 5xx errors | Operations | PENDING |
| INF-07 | The `public/storage` symlink is checked at start | Already warned by the entrypoint. DC files never use the public disk. | — | OK |

## 7. Status for the final report

- **Infrastructure readiness: BLOCKED.**
  - PR-02 to PR-05 need production access and Operations evidence.
  - INF-02 (PHP runtime version) must be resolved before any image built from this repository is deployed.
  - INF-01, INF-04 and INF-05 need Owner decisions.
- **What engineering could do is complete:**
  - the analysis;
  - the rehearsal;
  - the rollback fix;
  - the patch;
  - the procedures.
