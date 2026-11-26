# 31 — Deployment runbook (Debt Consolidation release candidate)

**For:** the person who deploys FynnEdge to production (Operations or the tech lead). Use it together with the Owner.

**Deploy the code with the service OFF.** Deploying does not launch the service; launching is a separate step (`34-…`).

**Do not start this runbook until all of the following are true** (`30-RELEASE-GATE-MASTER-REGISTER.md`):
- **D-gates done**: the code is reviewed and committed, CO-01.
- **PR-01 to PR-05 done.**
- **INF-02 resolved**: the PHP runtime image can run the locked dependencies (`27-…` §6).
- **The Owner has given written deployment authorisation (DP-01, gate F).** Record its reference here: ____________

> **Update (26 Nov 2026).** INF-01 is applied, so use **§3a**. §3b no longer applies. A start without `RUN_MIGRATIONS=true` refuses to boot while the 8 migrations are pending: that is intended. INF-02 is applied (PHP 8.5 runtime), so step 1.3, the staging build, is mandatory.

## 0. What changes in production

| Change | Detail |
| --- | --- |
| Code | Branch `feature/nov_24_redis_check`: commit `434ec8e` plus the release-candidate changes (`29-…` §6), once committed |
| Database | **8 additive migrations** (`27-…` §4.2): 18 new `debt_*` tables; reference data (document types, scoring v1, Debt Advisor role); 7 **draft** pages with 25 draft FAQs; renaming of one draft page. **No existing table is altered.** |
| Configuration | **No new required environment variables.** Optional: `DEBT_CONSOLIDATION_DRAFT_RETENTION_DAYS`, `DEBT_CONSOLIDATION_CALCULATOR_RETENTION_DAYS`. Check C-01 to C-12 in `27-…` §3. |
| Scheduler | Adds `debt-consolidation:enforce-retention` at 03:00 daily (needs PR-03) |
| Public site | **Nothing new visible while the service is off.** No DC pages (they 404), no header strip, menu, footer or homepage feature, no Savings Calculator, nothing in the sitemap. |

## 1. Before the window (T-1 day)

| # | Step | Who | Done |
| --- | --- | --- | --- |
| 1.1 | Read `migrate:status` on production (read-only) and confirm that exactly the 8 migrations in `27-…` §4.2 are pending, and nothing else. **If anything else is pending, stop and ask engineering.** | Operations | ☐ |
| 1.2 | On a **copy** of the production database, run `php artisan migrate --pretend` with the release image. Keep the SQL output. Then run `php artisan migrate` on that copy, open the admin, and run `php artisan migrate:rollback --pretend` to check that the rollback list is the same 8. | Operations | ☐ |
| 1.3 | Build the release image on a staging host and run the smoke tests (`33-…`, part A) there. This proves INF-02 is resolved. Also run `nginx -t` in the container. Check that a request with `Host: fynnedge.com` is served and that one with any other host gets no response (INF-04). | Operations | ☐ |
| 1.4 | Confirm PR-02 (mail), PR-03 (scheduler) and PR-04 (persistent, backed-up `storage/app`) with evidence (`27-…` §5) | Operations | ☐ |
| 1.5 | Agree the window, the people, and the go/no-go call with the Owner | Owner | ☐ |

## 2. Backup (PR-05), immediately before deploying

1. `mysqldump --single-transaction --routines --triggers <db> > fynnedge-<date>-pre-dc.sql`. Record its size and `sha256sum`.
2. Archive `storage/app/private` and `storage/app/public` (`tar czf …`). Record its size and checksum.
3. Store a copy of `APP_KEY` separately from both. Without it, encrypted files cannot be read.
4. Restore the dump into a scratch database and compare the row counts of 3 large tables. **If the restore fails, stop.**

## 3. Deploy the code (service stays off)

### 3a. If the explicit-migration entrypoint has been approved and applied (INF-01)

1. Build and push the release image.
2. **Migrate, once, on purpose:** `docker compose run --rm -e RUN_MIGRATIONS=true app true`. Watch the output for 8 `DONE` lines and no `FAIL`.
3. Start or replace the app container normally. It refuses to start if any migration is still pending.

### 3b. If the current entrypoint is kept (it migrates on start)

1. Build and push the release image.
2. Replace the app container **once**, and watch its log. The entrypoint runs `migrate --force` (8 `DONE` lines expected), then `config:cache`, `route:cache` and `view:cache`.
3. **Do not restart or scale out** until step 4 confirms the migration.

**If a migration fails:**
- **Stop.** Do not let the container restart-loop. Scale the app to 0 or stop it.
- Read the error, then follow `32-…` §3. The migrations are additive, so the old image keeps working on the partly migrated schema.

## 4. Verify the migration

1. `php artisan migrate:status`: the 8 migrations show **Ran**, in the same batch.
2. `php artisan tinker --execute 'echo App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::enabled() ? "ON" : "OFF";'` must print **OFF**.
3. Count the new pages: `select count(*) from pages where slug like 'debt-consolidation%' or slug in ('personal-debt-consolidation','business-debt-consolidation')`. Expect 7, all `draft`.

## 5. Restart the workers and caches

1. Restart the queue worker so it loads the new code: `supervisorctl restart queue-worker`, or restart the container.
2. If anything was cached before the deployment: `php artisan optimize:clear && php artisan optimize`. The entrypoint already caches on start.
3. Confirm the scheduler sees the new job: `php artisan schedule:list | grep debt-consolidation`.

## 6. Smoke test with the service OFF

Run `33-PRODUCTION-SMOKE-TEST-CHECKLIST.md`, part B. **Every check must pass.** Record the results in that checklist.

## 7. Close the window

1. Record in `30-…`:
   - gate F (deployment) completed: time, who, image tag, migration batch;
   - backup references;
   - smoke-test result.
2. Tell the Owner. **The service stays off until gate G (`34-…`).**

## 8. Abort criteria (go to `32-…`)

Abort and roll back if any of these happens:
- a migration fails;
- any existing page or journey fails in the smoke test;
- the error rate in the logs rises after the deployment;
- any DC page, the strip or the Savings Calculator is visible while the service is off.
