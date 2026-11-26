# 32 — Rollback and recovery plan

**For:** whoever operates production, with the Owner's agreement. Pick the **smallest** step that fixes the problem.

| Level | When | Effect | Data loss |
| --- | --- | --- | --- |
| 1. Switch the service off | Any customer-facing problem after go-live | All public DC entry points disappear at once: pages 404; strip, menu, footer, homepage feature and Savings Calculator hidden; new enquiries and submissions refused, even from forms already open. Existing cases stay visible to staff and to customers in the portal. | None |
| 2. Unpublish one page | A wording or content problem on one page | That page 404s; links to it disappear | None |
| 3. Roll back the code, keep the schema | A code defect, with the migrations fine | Previous image; the new tables stay, unused | None (DC data stays in the database, unused) |
| 4. Full rollback (code and schema) | The release must be removed entirely | Removes the DC tables and the unedited draft pages | **All DC data is deleted.** Export it first. |
| 5. Restore from backup | Data corruption or a failed migration that cannot be repaired | Back to the pre-deployment state | Everything after the backup |

## 1. Switch the service off (instant, no deployment)

1. Admin → *Website Settings → Debt Consolidation Settings*. Untick **"Debt Consolidation is live"** and save.
2. Check: `/debt-consolidation` returns 404; `/calculators/debt-consolidation` returns 404; the strip has gone from `/`.
3. Customers with open cases still see them in the portal. Staff keep working the cases.

## 2. Unpublish a page

*Website Pages → General Pages* → the page → status **Draft** → save.

## 3. Roll back the code only

1. Deploy the previous image.
   - **With the current entrypoint:** the old image runs `migrate --force`, which finds nothing to do, because the old code knows none of the new migrations. The new tables remain and are ignored.
   - **With INF-01 applied:** the old image has no pending migrations, so it starts normally.
2. Restart the queue worker.

**Notes:**
- Rows with the enquiry type or OTP purpose "debt consolidation" stay in the shared tables. The old code shows them as raw values.
- Queued DC notifications fail harmlessly: the old code lacks their classes. Clear them with `php artisan queue:flush` only if they are the only failed jobs; check `failed_jobs` first.

## 4. Full rollback (removes DC data)

**Only with the Owner's written decision**, because it deletes every DC case.

1. Export what must be kept:
   - `mysqldump <db> $(mysql -N -e "show tables like 'debt\_%'" <db>) > dc-export.sql`;
   - the `storage/app/private/debt-consolidation/` folder (encrypted; keep `APP_KEY`).
2. With the **new** image still running:
   1. `php artisan migrate:rollback --pretend`. The list must be exactly the 8 DC migrations of the release batch.
   2. Then `php artisan migrate:rollback`.
3. Then deploy the previous image (§3).

**What the full rollback removes and what it keeps:**

| Removed | Kept |
| --- | --- |
| The 18 `debt_*` tables | The *Debt Advisor* role and the Debt permissions (harmless; delete by hand if wanted) |
| The DC document types, unless another module now uses them | Any document type another module now uses |
| The 7 DC pages and their FAQs, **only while they are still drafts and unedited** | Pages an editor changed or published, which are kept on purpose; delete them by hand if needed |
| The analyser rename (reversed) | — |

**Evidence:**
- on MySQL at acceptance: migrate → rollback → re-migrate of migrations 1–7;
- 26 Nov 2026: the rename migration's rollback interaction was fixed and tested (`27-…` §4.3).

## 5. Restore from backup

1. Put the site in maintenance: `php artisan down --secret=<token>`.
2. Restore the database dump from PR-05 (`mysql <db> < fynnedge-<date>-pre-dc.sql`).
3. Restore `storage/app` from the PR-05 archive.
4. Make sure `APP_KEY` is the backed-up value.
5. Deploy the previous image.
6. `php artisan up`. Run `33-…` part A on the old version.

## 6. Recovery scenarios

| Scenario | Action |
| --- | --- |
| A migration fails part-way during deployment | Stop the app container (avoid a restart loop). The failed migration's transaction leaves earlier migrations applied. Either fix forward (engineering patch, redeploy) or run `migrate:rollback` for the applied DC migrations with the new image, then go to §3. |
| `APP_KEY` lost | DC documents and reports cannot be decrypted. Restore the key from the separate backup (PR-05). There is no other recovery. |
| Private storage lost | Restore `storage/app/private` from the backup. Database rows whose files are missing cannot be downloaded; the customer must re-upload. |
| Mail stops working | Customer and staff emails queue and fail. Fix the transport, then `php artisan queue:retry all` after checking `failed_jobs`. |
| Scheduler stops | The retention job does not run. No customer impact in the short term. Restart the scheduler. |
| A wrong lender policy was approved | Retire it (*Lender policies → Retire*), then re-run *Match lenders* on the affected cases. Matches already shortlisted stay visible to staff for review. |
