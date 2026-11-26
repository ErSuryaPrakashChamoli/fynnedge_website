# 12 — Deployment instructions

> **Superseded for this release (26 Nov 2026).** Use `31-DEPLOYMENT-RUNBOOK.md`, `32-ROLLBACK-AND-RECOVERY-PLAN.md` and `33-PRODUCTION-SMOKE-TEST-CHECKLIST.md`. The release now has **8** migrations (the Analyser rename was added), so roll back the release batch with `migrate:rollback` after checking `--pretend`, not `--step=7`. The desktop "Eligibility" link has also been removed at the Owner's request. This page is kept as the earlier record.

**Status:** release candidate on branch `feature/nov_24_redis_check`, tested locally, **uncommitted**. Nothing has been deployed and no production migration has run. **Production must not be changed without explicit authorisation.**

## 0. Read first: deploying the image IS migrating

The repository's Docker image (`Dockerfile`) builds the assets (`npm ci && npm run build`) and Composer dependencies itself. Its entrypoint (`docker/entrypoint.sh`) runs, on **every container start**:

`migrate --force` → `config:cache` → `route:cache` → `view:cache`

So starting the new image in production applies the 7 Debt Consolidation migrations immediately. Take the backup (step 1) **before** the new container starts.

## 1. Steps

1. **Back up**: production database dump; `storage/app/private` (documents and reports, encrypted); `APP_KEY`, stored separately and securely (without it the encrypted files cannot be read). Confirm the dump restores.
2. **Dry-run the DDL** against a copy of production (or production, read-only): `php artisan migrate --pretend`. Expect 7 additive migrations: 18 new `debt_*` tables; 8 new document types (any key that already exists is skipped); scoring configuration v1 (individual and business); the `Debt Advisor` role and permissions; 7 **draft** pages with SEO and 25 **draft** FAQs. No existing table is altered.
3. **Commit, build and deploy** the image (the build runs `npm run build` and `composer install`). The container start migrates and caches config, routes and views. `route:cache` and `view:cache` were verified on this code locally.
4. **Queue workers**: restart them so they load the new code (supervisor's `queue:work` uses `--max-time=3600`; restart rather than wait). Customer notifications and staff alerts are queued after commit on the existing `database` queue.
5. **Scheduler**: the site's scheduled jobs (newsletter every minute, CRIF/Equifax/Experian jobs and others) run through `schedule:run`, which is **not** in this image's supervisor config, so it must run on the host or elsewhere. Confirm it does: Debt Consolidation adds `debt-consolidation:enforce-retention` daily at 03:00.
6. **Smoke test with the service still OFF** (section 3).
7. **Launch** only after the business approvals in `16-PRE-PRODUCTION-ACCEPTANCE-REPORT.md` §6.

**Environment**: no new variables are required. Optional: `DEBT_CONSOLIDATION_DRAFT_RETENTION_DAYS` (180) and `DEBT_CONSOLIDATION_CALCULATOR_RETENTION_DAYS` (90). Production must already provide a working mail transport for customer and staff emails (local uses `log`). The cache store must support locks (the `database` store does; used to stop duplicate cases).

**Storage**: files are written to `storage/app/private/debt-consolidation/` by PHP-FPM (uploads, reports) as `www-data`. The entrypoint creates `storage/app/private` and gives `www-data` ownership. The directory must be on **persistent** storage included in backups.

## 2. Feature activation (business-controlled, no deployment needed)

| Control | Where | Effect |
| --- | --- | --- |
| Service on/off | Website Settings → Debt Consolidation Settings → "Debt Consolidation is live" | Off (the default): pages 404, no site-wide promotion, and new enquiries, cases and submissions are refused, even from a form already open. Existing cases remain visible in the portal and to staff. |
| Individual / business intake | Same screen | Each applicant type can be paused separately |
| Pages and FAQs | Website Pages → General Pages | Each of the 7 pages, and its FAQs, published individually; links to an unpublished page are never shown |
| Homepage feature wording | Website Pages → Homepage & sections → Marketing Sections | Optional |
| Lender policies | Debt Consolidation → Lender policies | Matching uses only approved (second-person) policies |

Launch order: legal sign-off on wording → lender policies entered and approved → pages and FAQs reviewed and published → team email set → switch the service on. Switching it off again removes all public entry points at once.

## 3. Production smoke-test plan

With the service **OFF** (immediately after deployment):

1. `/`, a loan page, `/faqs`, `/contact`: load normally. There is no Debt Consolidation link in the header, strip or footer, and the desktop menu still shows "Eligibility".
2. `/debt-consolidation` and `/debt-consolidation/calculator` return 404. `sitemap.xml` has no Debt Consolidation pages.
3. Admin as super admin: the Debt Consolidation dashboard and every screen open. Administration → Roles → "Debt Advisor" shows the Debt permissions, including the Custom permissions tab, and saving it changes nothing.
4. General Pages → the hub → Preview: the page renders, with the forms hidden.
5. The existing credit-score, enquiry and EMI calculator journeys still work, and an OTP still arrives.
6. The logs show no new errors. A test notification reaches the queue and is processed.

After the authorised switch-on, in a controlled window: submit one test enquiry using staff's own number. Check:

- the enquiry appears in the lead inbox, the customer email arrives, and the staff alert arrives;
- the case is visible in the portal;
- then withdraw the test case.

## 4. Rollback

1. **Instant**: switch the service off (above). No deployment is needed.
2. **Full**:
   1. Export any real case data you want to keep.
   2. With the **new** code still deployed, run `php artisan migrate:rollback --step=7`. This removes the module's tables, the still-unedited draft pages, and the document types nothing else uses; it never deletes a type another module now references (tested). It was verified on MySQL (migrate → rollback → re-migrate).
   3. Only then deploy the previous image.

   This order matters because the old image's entrypoint does not know these migrations, so it cannot roll them back.
3. The `EnquiryType::DebtConsolidation` and `MobileOtpPurpose::DebtConsolidation` values stay on existing enquiry and OTP rows; older code shows them as raw values.
