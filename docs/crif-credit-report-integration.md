# CRIF credit reports (Surepass)

How authorised FynnEdge staff fetch and read a customer's full CRIF High Mark credit report through Surepass. This covers the pieces, how duplicate paid pulls are prevented, how to configure and deploy it, and what to do when a request goes wrong.

> **The one rule.** Every report fetch is a **paid** Surepass call. Nothing in this module retries a call automatically. A request whose outcome is uncertain (timeout, gateway error, a success without a report) waits for a human to **reconcile** it against the Surepass dashboard. It is never re-sent blindly.

---

## 1. Scope

- **Covered:** employee-initiated CRIF reports in the admin panel, linked to an existing **Customer** and optionally to the **Application** (journey session) it came from.
- **Also covered (opt-in):** the public "Free CRIF Score" page (`/credit-score/crif`) can show a **real** CRIF score through Surepass's score endpoint (§14). It is off by default and shows the demo score until switched on.
- **Not changed:** the CIBIL, Experian and Equifax score pages stay on `DemoCreditScoreProvider`, and the loan journey's bureau check still runs on `NullCreditBureauProvider`. There was no live CIBIL integration in the codebase to change.
- CIBIL and CRIF are never interchangeable. Reuse only ever matches a previous **CRIF** report for the same PAN.

## 2. Architecture

Everything sits in the existing `app/Modules/CreditBureau/` module, next to the consent model it reuses.

| Component | Responsibility |
| --- | --- |
| `Actions/RequestCrifReport` | The only way a request starts. Checks permission, the on/off switch, the PAN, name and mobile, and the consent. Then, under a per-PAN lock: blocks if a request is in flight, reuses a report from inside the reuse period, refuses to resend details Surepass already rejected, and otherwise creates a pending row and queues the call. |
| `Jobs/FetchCrifReport` | Queued, `$tries = 1`. Calls `ProcessCrifReport`. `failed()` resolves a job that died. |
| `Actions/ProcessCrifReport` | Claims the row with a conditional `pending → processing` UPDATE, so only one execution ever calls Surepass. Stores success, failure or *reconciliation required*. |
| `Actions/ReconcileCrifReport` | Records an admin's finding for an uncertain attempt and releases the PAN. |
| `Actions/FetchCrifReportPdf` | Gets the provider PDF once, stores it encrypted, and serves later downloads from that copy. |
| `Services/SurepassCrifService` | The HTTP client. It has no retry and no `->throw()`, and it classifies every answer (§4). |
| `Services/CrifReportDocument` | A read-only parser over a stored response, used by the report screen and to extract header fields. It tolerates CRIF's object-or-list-or-`""` blocks and drops nothing. |
| `Services/PanNumber`, `CrifRequestFingerprint` | PAN normalisation, validation, HMAC hash and mask; an HMAC of the request details. |
| `Services/CrifSettings`, `CrifRoles` | Admin settings (§6); permissions and the Credit Officer role (§7). |
| `Models/CrifReport` | One row per paid attempt. Sensitive columns are encrypted, hidden and kept out of the audit trail. |
| `Models/CreditConsent` (extended) | Now also holds consents an employee records (method, evidence reference, recorded by). |
| `app/Filament/Resources/CrifReports` | List, full report view and the workflow actions (`CrifReportActions`). |
| `app/Filament/Pages/CrifDashboard`, `CrifSettings` | Operations overview and business settings. |
| `app/Filament/RelationManagers/CrifReportsRelationManager` | The "CRIF reports" tab on Customers and Applications. |
| `crif:flag-stale`, `crif:enforce-retention` | Scheduled housekeeping (§9). Neither command calls Surepass. |

## 3. Surepass API as integrated

```
POST {SUREPASS_BASE_URL}/api/v1/credit-report-crif/fetch-report        (full report)
POST {SUREPASS_BASE_URL}/api/v1/credit-report-crif/fetch-report-pdf    (PDF link; body adds "raw": false)
POST {SUREPASS_BASE_URL}/api/v1/credit-report-crif/score               (score only — the public Free CRIF Score page, §14)
Authorization: Bearer {SUREPASS_BEARER_TOKEN}
Content-Type: application/json

{ "first_name": "…", "last_name": "…", "mobile": "10 digits", "pan": "ABCDE1234F", "consent": "Y" }
```

- `"consent": "Y"` can only be sent through a `CrifSubject`, which cannot be built without a `CreditConsent` in the *Given* state.
- The timeout is `SUREPASS_TIMEOUT` (default 60 s) and the connect timeout is `SUREPASS_CONNECT_TIMEOUT` (default 10 s). There are no retries.
- **Response shape.** Surepass's console docs could not be read programmatically. The parser follows the CRIF payload as published by a Surepass reseller: `data.credit_score` and `data.credit_report` holding `HEADER`, `PERSONAL-INFO-VARIATION`, `ACCOUNTS-SUMMARY`, `RESPONSES.RESPONSE[].LOAN-DETAILS`, `INQUIRY-HISTORY`, `SCORES` and `EMPLOYMENT-DETAILS`. Any section it does not recognise is still shown under "Other report sections", and the complete response is always kept. **Check the first live response against the report screen** (§11).
- **PDF.** The link is read from `credit_report_link` (also accepted: `pdf_link`, `report_link`, `pdf_url`, `link`, `url`; https only). The PDF is downloaded server side **without** the bearer token, checked to start with `%PDF`, and stored encrypted. The temporary link is never shown or stored.

## 4. How each answer is classified

| Surepass answer | Error code | Outcome | PAN released? | Retry offered? |
| --- | --- | --- | --- | --- |
| 2xx, `success: true`, non-empty `data.credit_report` | — | **success** | yes | — |
| 2xx, `success: true`, no report | `missing_report_payload` | reconciliation required | **no** | no |
| 2xx, not JSON, or no `success` flag | `malformed_response` | reconciliation required | **no** | no |
| 2xx, `success: false` | `provider_rejected` / `no_record_found` / `insufficient_balance` (by `message_code`) | failed | yes | only for balance |
| 401 / 403 | `authentication_failed` | failed + alert | yes | yes, once the token is fixed |
| 402 | `insufficient_balance` | failed + alert | yes | yes |
| 429 | `rate_limited` | failed | yes | yes |
| 400 / 422 | `invalid_request` or `no_record_found` | failed | yes | no (correct the details) |
| 503 | `provider_unavailable` | failed | yes | yes |
| 500 / 502 / 504 / other 5xx | `provider_error` | reconciliation required | **no** | no |
| Read timeout (cURL 28 "Operation timed out") | `timeout` | reconciliation required | **no** | no |
| DNS, refused, TLS or connect-phase timeout | `connection_failed` | failed | yes | yes |
| Connection dropped after sending | `unexpected_error` | reconciliation required | **no** | no |
| Token or base URL missing, or integration switched off | `not_configured` | failed, nothing sent | yes | yes |
| Consent withdrawn after queueing | `consent_invalid` | failed, nothing sent | yes | no |

Stored and shown: the error code, a fixed safe message, the HTTP status and Surepass's `message_code`. A failed response's body is never stored, logged or shown.

## 5. Request states

```
pending ──▶ processing ──▶ success ──▶ expired (retention purge)
   │             ├──────▶ failed
   └─▶ failed    └──────▶ reconciliation_required ──(admin)──▶ failed
```

`pending`, `processing` and `reconciliation_required` are **in flight**. While a row is in flight its `in_flight_key` holds the PAN hash, and the unique index `crif_reports_in_flight_unique` lets only one such row exist per PAN. That is the database-level duplicate guard; the per-PAN cache lock in `RequestCrifReport` keeps simultaneous clicks orderly in front of it.

## 6. Duplicate prevention and reuse (what an employee sees)

1. **Fetch CRIF Report** (Customer page, Application page, or the CRIF list).
2. If the PAN has a request in flight, the employee is taken to it. Nothing is sent, and `duplicates_blocked` is counted.
3. Otherwise, if the PAN has a successful report inside the **reuse period**, that report is shown. Nothing is sent, and `times_reused` is counted.
4. If the last request with **exactly these details** was rejected (invalid details, no record, declined), the request is refused. Corrected details count as a new request.
5. Otherwise a pending request is created and queued, and the page refreshes itself until the report arrives.

**Fetch Fresh CRIF Report** needs the `FetchFresh:CrifReport` permission and a reason of at least 15 characters. It skips step 3 only; an in-flight request still blocks it. The reason is stored and audited.

**Retry** is offered only on a failed attempt that Surepass is known not to have processed (retryable codes, or reconciled as *not processed*), and only once per attempt.

**PAN normalisation.** Whitespace is removed and letters are upper-cased before validation (`^[A-Z]{5}[0-9]{4}[A-Z]$`), hashing or storage.

**Multiple customer records sharing a PAN.** Reuse and blocking are keyed by PAN, so a second customer record with the same PAN sees the existing report. The audit event marks `other_customer_record: yes`. Records are never merged.

## 7. Permissions

| Permission | Allows | Credit Officer role |
| --- | --- | --- |
| `ViewAny:CrifReport`, `View:CrifReport` | CRIF list, report screen, CRIF tabs | ✔ |
| `Create:CrifReport` | Fetch a report (with reuse) and retry | ✔ |
| `DownloadPdf:CrifReport` | Get and download the PDF | ✔ |
| `View:CrifDashboard` | CRIF Dashboard | ✔ |
| `FetchFresh:CrifReport` | Fresh-report override | — |
| `Reconcile:CrifReport` | Reconcile uncertain attempts | — |
| `ViewRaw:CrifReport` | Complete unmasked JSON | — |
| `View:CrifSettings` | CRIF Settings page | — |
| `View:AuditLog` (existing) | History tab: who viewed or acted on a report | — |

`super_admin` has everything. Grant the others under **Access Control → Roles**. Nobody can edit or delete a report attempt.

## 8. Consent

A request needs one of:

- a **website application consent** (the journey's credit-check checkbox) for the same customer, with a PAN that matches (if one was recorded); or
- **consent recorded now** by the employee: method (OTP, signed form, recorded call, email), date and time, an evidence reference, and an attestation checkbox.

In both cases the consent must be *Given* and no older than **Consent validity (days)**. Recorded consents are stored in `credit_consents` with `customer_id`, `method`, `evidence_reference` and `recorded_by`. Each report screen shows the consent it used.

## 9. Data protection

- **Encrypted at rest** (Laravel `encrypted` casts, APP_KEY): PAN, first and last name, mobile, and the complete provider response. Stored PDFs are encrypted files on the private `local` disk (`storage/app/private/crif-reports/`).
- **Lookups** use `pan_hash`, an HMAC-SHA256 keyed from APP_KEY. **Rotating APP_KEY** makes stored reports unreadable and breaks reuse and duplicate matching. Before rotating, use `APP_PREVIOUS_KEYS` for decryption, and plan to re-hash `pan_hash`, `in_flight_key` and `request_fingerprint`.
- **Masked on screen:** the PAN shows its last 4 characters (`XXXXXX234F`), as do the mobile, account numbers, and identity and phone variations. Full values appear only in the raw JSON modal (`ViewRaw:CrifReport`).
- **The PAN filter** on the CRIF list matches by hash and is deliberately not mirrored into the URL.
- **Logs** carry the report's public id, masked PAN, outcome code and HTTP status only. They never contain the PAN, mobile, names, response body or token.
- **Audit:** `audit_logs` (the existing History tab) records creation and every status change, minus sensitive columns, plus `viewed`, `raw_viewed`, `reused`, `duplicate_blocked`, `processing_started`, `interrupted`, `pdf_fetched`, `pdf_downloaded` and `pdf_failed`.
- **Retention:** `crif:enforce-retention` (daily, 02:30) erases the response, score, PDF, PAN, names and mobile of attempts older than **Retention period (days)**. Successful reports become *Expired*. The masked PAN, status, requester, provider ids and timestamps stay as the billing and audit record.
- **Customer deletion** keeps the CRIF attempt rows (the `customer_id` becomes null) until retention purges them.

## 10. Configuration

**Environment** (server only; see `.env.example`):

| Variable | Default | Notes |
| --- | --- | --- |
| `SUREPASS_BASE_URL` | `https://kyc-api.surepass.app` | |
| `SUREPASS_BEARER_TOKEN` | none | **Secret.** Never commit it, put it in a Setting, or log it. |
| `SUREPASS_TIMEOUT` | `60` | Seconds. The job's own timeout is 150 s, so keep this below ~140. |
| `SUREPASS_CONNECT_TIMEOUT` | `10` | Seconds. |

**Admin → Credit Bureau → CRIF Settings:**

| Setting | Default |
| --- | --- |
| CRIF report requests enabled | **off** |
| Free CRIF Score page uses real scores | **off** (§14) |
| Reuse period (days) | 30 |
| Consent validity (days) | 30 |
| Retention period (days) | 365 (never shorter than the reuse period) |
| Operational alert email | blank (no alerts) |

## 11. Deploying

1. Merge and deploy as usual. `php artisan migrate --force` adds `crif_reports`, widens `credit_consents` and creates the CRIF permissions and the **Credit Officer** role. All three migrations are additive.
2. Rebuild admin assets (`npm run build`) so the report screen's styles are compiled into the Filament theme.
3. Set `SUREPASS_BEARER_TOKEN` (and optionally the timeouts) in the server environment, then run `php artisan config:clear` (or `config:cache`).
4. Make sure the **queue worker** is running (`docker/supervisor/supervisord.conf` already runs `queue:work`). Reports are fetched on the queue.
5. Make sure the **scheduler** runs. The Docker image's supervisor config has no `schedule:work` program, so add one (or a host cron for `php artisan schedule:run`). Without it, `crif:flag-stale` and `crif:enforce-retention` never run, and nor do the existing newsletter and translation schedules.
6. Assign roles: give the credit team **Credit Officer**, and grant `FetchFresh`, `Reconcile`, `ViewRaw` and `View:CrifSettings` deliberately.
7. Open **CRIF Settings**, check the defaults, set the alert email and switch requests **on**.
8. **First live check:** fetch one report for a consenting test customer. Compare the report screen with the Surepass dashboard or the raw JSON, especially accounts, inquiries and score factors. Then try **Get PDF from Surepass** once and confirm which key held the link.

## 12. Operations

- **Reconciliation required.** Open the request, look up the call in the Surepass dashboard usage log around the request time, and choose **Reconcile**:
  - *Not processed (no charge):* the attempt becomes retryable.
  - *Processed and charged, no report:* the attempt stays failed. Request again only if a report is genuinely needed.
- **Stuck in pending or processing.** `crif:flag-stale` (every 5 minutes) fails pending rows older than 15 minutes, since they were never sent and are retryable. It moves processing rows older than 15 minutes to reconciliation. Run it by hand with `php artisan crif:flag-stale`.
- **Every request fails with "token rejected" or "insufficient balance".** Fix the token or top up the balance, then use **Retry** on the affected attempts.
- **The dashboard** shows totals, reuse and blocked duplicates, fresh overrides, errors by code, per-employee counts, 14 days of volume and recent activity.

## 13. Tests

Every Surepass call in the suite goes through `Http::fake()` with `Http::preventStrayRequests()` (`fakeSurepassCrif()` in `tests/Pest.php`), and `phpunit.xml` blanks the token. The fixture `tests/Fixtures/Surepass/crif-report-success.json` is fabricated.

```
php artisan test --compact tests/Feature/CreditBureau tests/Feature/Filament/CrifReportAdminTest.php
```

## 14. Public Free CRIF Score page

With **CRIF Settings → Free CRIF Score page uses real scores** switched on (and the token set), `/credit-score/crif` shows a real score instead of the demo one. It uses `SurepassCrifScoreProvider`, routed by `SubmitCreditScoreCheck` for the CRIF bureau only.

- **Flow is unchanged:** the visitor verifies their mobile by OTP, then gives their name, date of birth, PAN and the consent tick. Each OTP pays for at most one check. A real check also needs a first **and** last name, since Surepass requires both.
- **Cost control:** the same person (same PAN **and** same verified mobile) checking again inside the reuse period sees their stored score, with no call. The reused row records `reused_from`. Matching on the mobile too means typing someone else's PAN never shows their stored score. A per-person lock stops two tabs paying twice.
- **Uncertain outcomes** (timeout, gateway error) are stored as *Pending*. The same person is told to try again later, and nothing is re-sent for an hour.
- **What the visitor sees:**
  - a live score (300–900);
  - "No score yet" when CRIF returns no valid score (e.g. `-1` for no credit history);
  - a no-match message when Surepass rejects the details;
  - a generic "try later" for anything else.
  Provider text is never shown.
- **Stored** in `credit_score_checks` (provider `surepass`): the score, Surepass's client id and message code. Never the response body, which echoes the PAN, name and mobile.
- **Admin:** Credit Score Checks has a *Source* column (Live / Demo).
- **Before switching on**, budget for it: every new visitor check is a paid call. Watch the Surepass usage dashboard for the first days.
