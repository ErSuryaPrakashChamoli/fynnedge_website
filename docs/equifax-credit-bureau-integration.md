# Equifax credit bureau (Surepass)

How FynnEdge staff, internal modules and the public site get Equifax credit scores and full reports through Surepass. This covers the module's parts, its API, how paid pulls are kept from being duplicated, configuration, deployment and operations.

> **The one rule.** Every Equifax pull is a **paid** Surepass call. Nothing here retries one automatically. A request whose outcome is uncertain (timeout, gateway error, a success without the expected payload) waits for an admin to **reconcile** it against the Surepass dashboard.

---

## 1. What the Surepass plan includes

Checked on 16 Oct 2026 with empty, non-billable requests. Repeat the check any time with `php artisan equifax:check-access`.

| Surepass endpoint | Plan | Used for |
| --- | --- | --- |
| `POST /api/v1/credit-report-v2/fetch-report` | **Allowed** | Full reports (admin, API, other modules); the public score page |
| `POST /api/v1/credit-report-v2/score` | **Not enabled** (401 "not valid for this API/Scope") | Score-only requests |
| `POST /api/v1/credit-report-v2/fetch-pdf-report` | **Not enabled** (same) | PDF fallback |

Consequences until Surepass enables the other two:
- A **score-only** request fails with *API not enabled on the Surepass account* and nothing is charged, unless a recent full report can answer it (§6). Request a full report instead.
- The **public Free Equifax Score page** reads the score from the full report (`SUREPASS_EQUIFAX_PUBLIC_SCORE_SOURCE=report`, the default).
- **PDF** works only when the report carries a usable `credit_report_link` (it was empty in Surepass's sample).

Surepass accepts `gender` = `male` or `female` only (lower case) and `id_type` = `pan`.

## 2. Architecture

Equifax is its own module, `app/Modules/Equifax/`. It shares no records, services, controllers or admin screens with CRIF.

| Component | Responsibility |
| --- | --- |
| `Actions/RequestEquifaxCheck` | The only entry point for a paid pull. Checks permission, the on/off switch, the details (PAN, name, mobile, gender) and consent. Then, under a per-PAN lock: block when an equivalent request is in flight, reuse within the product's reuse period, refuse to resend rejected details, otherwise create a pending row and queue the job. |
| `Jobs/FetchEquifaxRequest` | Queued, `$tries = 1`. `failed()` resolves a job that died. |
| `Actions/ProcessEquifaxRequest` | Claims the row with a conditional `pending → processing` UPDATE, so exactly one paid call happens per row. Stores the success, failure or reconciliation outcome. |
| `Actions/ReconcileEquifaxRequest` | Records an admin's finding for an uncertain attempt and releases the lock. |
| `Actions/FetchEquifaxPdf` | Returns the stored copy, else the report's own link, else the PDF endpoint (at most once per report). The PDF is stored encrypted. |
| `Services/SurepassEquifaxService` | The three `credit-report-v2` operations; classifies every answer (§7). |
| `Services/EquifaxReportDocument` | Read-only parser. It finds sections by name anywhere in the response (InquiryResponseHeader, CCRResponse → CIRReportDataLst → CIRReportData → IDAndContactInfo, RetailAccountDetails, ScoreDetails, Enquiries…), masks identifiers on screen and never drops a field. |
| `Services/EquifaxCreditService` | **The reusable application service for other modules** (§9). |
| `Services/EquifaxSettings`, `EquifaxRoles`, `EquifaxFingerprint` | Admin settings; permissions; HMAC keys for request details and in-flight locks. |
| `Models/EquifaxCreditRequest` | One row per paid attempt. Sensitive columns are encrypted, hidden from serialisation and kept out of the audit trail. |
| `Policies/EquifaxCreditRequestPolicy` | One policy for the admin, the API and the service. |
| `app/Http/Controllers/Api/V1/EquifaxController` + `routes/equifax.php` | The internal JSON API (§8). |
| `app/Filament/Resources/EquifaxCreditRequests`, `Pages/Equifax*` | The Equifax admin section (§10). |
| `app/Modules/CreditScore/Providers/SurepassEquifaxScoreProvider` | Real scores on the public Equifax page (§11). |

**Shared with CRIF, deliberately** (common infrastructure, not CRIF logic):
- `CreditBureau/Services/SurepassClient`: the Surepass transport (token, timeouts, no retries, connection-failure handling, PDF download without the token, access probe). `SurepassCrifService` was refactored onto it with no change in behaviour.
- `CreditBureau/Services/CreditConsentGate` + `CreditConsentInput`: consent validation and recording against the existing `credit_consents` table.
- `App\Filament\Schemas\CreditConsentFields`: the consent part of request forms.
- `PanNumber`, `MobileNumber`, `audit_logs` (`Auditable`), `Setting`, Shield roles (the **Credit Officer** role).

## 3. Data model: `equifax_credit_requests`

There is one row per paid attempt, for either product, and history is never overwritten. This follows CRIF's proven single-table design rather than separate request and report tables. Audit events go to the shared `audit_logs` table, as the project rule requires (one audit system), not a separate Equifax audit table.

Key columns:
- **Identity and links:** `public_id` (UUID; the only id in URLs and the API), `customer_id`, `journey_session_id` (lead / application), `credit_consent_id`, `previous_request_id` (retry or fresh chain), `requested_by`, `channel` (`admin` / `api`).
- **Request:** `product` (`score` | `report`), `request_type` (`standard` | `retry` | `fresh`), `fresh_reason`, `status`.
- **Duplicate protection:** `pan_hash` (HMAC), `in_flight_key` (unique; HMAC of product + PAN while in flight, else NULL), `request_fingerprint`.
- **Customer details:** `pan_masked`, `mobile_masked`, plus `pan_encrypted`, `full_name` and `mobile` (all encrypted), and `gender`.
- **Result:** `score`, `provider_client_id`, `provider_report_id` (ReportOrderNO), `provider_message_code`, `http_status`, `report_date`, `raw_response` (complete response, encrypted), `summary` (derived headline figures, encrypted, separate from the original).
- **Errors and reconciliation:** `error_code`, `error_message` (safe text), `retryable`, `times_reused`, `duplicates_blocked`, `reconciliation_*`.
- **PDF and timing:** `pdf_path`, `pdf_fetched_at`, `pdf_error_code`, `requested_at`, `processing_started_at`, `completed_at`, `purged_at`.

Other schema changes:
- `credit_score_checks.gender`, for the public Equifax page.
- Permissions migration: `EquifaxRoles::grant()`.

All migrations are additive and reversible.

## 4. Request states

```
pending ──▶ processing ──▶ success ──▶ expired (retention purge)
   │             ├──────▶ failed
   └─▶ failed    └──────▶ reconciliation_required ──(admin)──▶ failed
```

`pending`, `processing` and `reconciliation_required` are in flight. They hold `in_flight_key`, so the database allows only one unresolved request **per PAN per product**.

## 5. Consent

Every pull needs one of:
- an existing consent for this customer, either the website journey checkbox or one recorded earlier, with a matching PAN if the consent names one; or
- consent recorded now: method (OTP, signed form, recorded call, email), timestamp, evidence reference and an attestation.

The consent must be *Given* and no older than **Consent validity (days)**. `"consent": "Y"` can only be sent through an `EquifaxSubject`, which cannot be built without a Given consent.

## 6. Duplicate prevention and reuse

Checked in this order, under a per-PAN lock, inside a transaction:

1. **Equivalent request in flight → BLOCKED** (`duplicates_blocked` + 1). A report in flight also blocks a score request; a score in flight does not block a report.
2. **Reusable result → REUSED** (`times_reused` + 1), no call:
   - a **report** request is answered only by a full report inside the **report reuse period**;
   - a **score** request is answered by a score *or* a full report inside the **score reuse period** (a report carries the score);
   - a score result never answers a report request;
   - CRIF and CIBIL results are never used.
3. **Same details already rejected** (same PAN, name, mobile and gender fingerprint) → refused. Corrected details are a new request.
4. Otherwise → **QUEUED**: one paid call.

**Fetch fresh** skips step 2 only. It needs `FetchFresh:EquifaxCreditRequest` and a reason of at least 15 characters, which is stored and audited. **Retry** is allowed once, only for a failure Surepass did not process. A PAN is never blocked permanently: once a reuse period ends, a normal request pulls again.

## 7. How Surepass answers are classified

| Answer | Code | Outcome |
| --- | --- | --- |
| 2xx, `success: true`, expected payload | — | success |
| 2xx `success: true` without report / score | `missing_report_payload` / `missing_score` | reconciliation |
| 2xx not JSON, or no `success` flag | `malformed_response` | reconciliation |
| 2xx `success: false` | `provider_rejected` / `no_record_found` / `insufficient_balance` | failed |
| 401/403 "not valid for this API/Scope" | `api_not_enabled` | failed, alert, retryable |
| other 401/403 | `authentication_failed` | failed, alert, retryable |
| 402 | `insufficient_balance` | failed, alert, retryable |
| 429 / 503 | `rate_limited` / `provider_unavailable` | failed, retryable |
| 400 / 422 | `invalid_request` / `no_record_found` | failed |
| 500 / 502 / 504 | `provider_error` | reconciliation |
| read timeout | `timeout` | reconciliation |
| DNS, refused, TLS or connect timeout | `connection_failed` | failed, retryable |

## 8. Internal API (`routes/equifax.php`)

- **Authentication:** the application's existing staff login (session, `web` group, with CSRF on POST). The app has no API tokens (no Sanctum). Every call is authorised by the Equifax policy *and* the customer's own policy.
- **Rate limit:** 30 calls a minute per employee (`throttle:equifax-api`).
- **Envelope:** `{ success, message, data | error_code }`.
- **Status codes:**
  - 202 queued; 200 reused or read; 409 an equivalent request is in flight;
  - 422 validation failed or request refused; 403 not permitted; 401 signed out (JSON, never a redirect);
  - 404 unknown id; 429 rate limited.
- **Inside the application:** modules should call `EquifaxCreditService` instead of making HTTP requests to their own API.

| Method & path | Purpose | Permission |
| --- | --- | --- |
| `POST /api/v1/equifax/score` | Request a score (may reuse / block / queue) | `Create` + `View:Customer` |
| `POST /api/v1/equifax/reports` | Request a full report | `Create` + `View:Customer` |
| `GET /api/v1/equifax/reports/{id}` | A request. `?include=report` adds the full report (masked; needs `ViewReport`); `?include=raw` adds the unmasked response (needs `ViewRaw`). Both are audited. | `View` + `View:Customer` |
| `POST /api/v1/equifax/reports/{id}/pdf` | The PDF (`application/pdf`) | `DownloadPdf` |
| `GET /api/v1/equifax/requests/{id}/status` | Status only | `View` |
| `GET /api/v1/equifax/customers/{customer}/reports` | The customer's requests (paginated, `per_page` ≤ 50) | `ViewAny` + `View:Customer` |

Request body (`score` and `reports`):
```json
{
  "customer_id": "9b2c…-uuid",
  "journey_session_id": "optional application uuid (must belong to the customer)",
  "pan": "ABCDE1234F",
  "gender": "male",
  "name": "optional — defaults to the customer's name",
  "mobile": "optional — defaults to the customer's phone",
  "consent": { "credit_consent_id": "uuid" },
  "fresh": false,
  "fresh_reason": "required when fresh is true (15+ characters)"
}
```

The consent can instead be recorded in the same call:
```json
"consent": { "method": "recorded_call", "evidence_reference": "CALL-1234", "consented_at": "2026-10-16T09:30:00+05:30", "attested": true }
```

Response (202):
```json
{
  "success": true,
  "message": "Equifax report requested. It usually arrives within a minute.",
  "outcome": "queued",
  "data": {
    "id": "…uuid", "customer_id": "…", "application_id": null, "product": "report",
    "request_type": "standard", "status": "pending", "status_label": "Pending", "score": null,
    "pan_masked": "XXXXXX234F", "mobile_masked": "******3210",
    "requested_at": "2026-10-16T09:31:02+00:00", "completed_at": null, "report_date": null,
    "reusable": false, "reusable_until": null, "provider_reference": null, "error": null, "pdf_stored": false
  }
}
```

The response never includes the full PAN or mobile, the token, Surepass's error text, the stored PDF path, or the temporary PDF link.

## 9. Reuse from other modules

```php
$equifax = app(\App\Modules\Equifax\Services\EquifaxCreditService::class);

$equifax->latestScore($customer); // ['score' => 761, 'product' => 'report', 'fetched_at' => …, 'reusable' => true, 'request_id' => …] | null
$equifax->summary($customer);     // headline figures of the newest full report | null
$equifax->latest($customer, EquifaxProduct::Report, reusableOnly: true);
$equifax->request(new EquifaxCheckRequest(...), $user); // the only call that can spend money
```

The read methods never call Surepass, so they are safe on every page load. `request()` applies the same permission, consent, duplicate and reuse rules as the admin.

## 10. Admin (Filament): the **Equifax** navigation group

| Menu item | What it is |
| --- | --- |
| Equifax Dashboard | Score/report request counts, successful, failed, in progress, needs reconciliation, duplicates prevented, results reused, fresh requests, per-employee table, provider errors, recent activity |
| Equifax Credit Reports | Full-report tab of the request list |
| Equifax Requests | Every attempt. Tabs: all, full reports, scores, failed, needs reconciliation. Filters: customer, PAN (matched by hash, never in the URL), status, type, request, employee, date range. |
| Equifax Failed Requests | Failed tab (badge = awaiting reconciliation) |
| Equifax Settings | On/off switches, reuse periods, consent validity, retention, alert email |
| Equifax Audit History | Every Equifax event from `audit_logs` (needs `View:AuditLog`) |

**Request page:**
- Shown to anyone who may view the request: overview, score and scoring factors, processing history.
- With `ViewReport`, the full report: personal information, identities (masked), addresses, phones (masked), emails, account and enquiry summaries, accounts with their 48-month history, enquiries, what Equifax was asked, and every other section.
- Without it: score and status only.
- Actions: PDF, raw JSON (`ViewRaw`), retry, reconcile, fresh pull. The History tab is the audit log.

**Customers and Applications** get an **Equifax** menu (view latest, fetch report, check score, fetch fresh) and an **Equifax** tab. The customer list also gains an Equifax status column.

### Permissions

| Permission | Credit Officer |
| --- | --- |
| `ViewAny`, `View`, `ViewReport`, `Create`, `DownloadPdf` `:EquifaxCreditRequest`; `View:EquifaxDashboard` | ✔ |
| `FetchFresh`, `Reconcile`, `ViewRaw` `:EquifaxCreditRequest`; `View:EquifaxSettings` | — (grant deliberately) |
| `View:AuditLog` (existing) | — |

## 11. Public Free Equifax Score page (`/credit-score/equifax`)

**Equifax Settings → "Free Equifax Score page uses real scores"** (off by default) switches the page from the demo score to real scores:
- The page asks for **gender** (male/female), which Equifax requires.
- The visitor's details (OTP-verified mobile, name, PAN, consent tick) go to `credit-report-v2/fetch-report`.
- **Every paid website check is also an Equifax request** (channel `website`, `RecordWebsiteEquifaxCheck`).
  - The row is created as *processing* before the call, with a consent record (method OTP, evidence = the score check's id). A crash mid-call is then caught by `equifax:flag-stale`.
  - The answer is stored by the same rules as staff requests, so the full report is kept encrypted and shows under Equifax Credit Reports, the dashboard ("Website visitors"), failed / reconciliation lists and audit.
  - The row is linked to the newest customer with the same mobile, if any. It holds no in-flight key, and as a successful report it can answer a later staff request for the same PAN inside the reuse period.
  - `credit_score_checks` keeps only the score, client id, message code and `equifax_request` (the request's id).
- The same PAN and mobile checking again inside the score reuse period sees the stored score at no cost. Uncertain answers are held for an hour.
- The visitor sees "Live score from Equifax", "No score yet", a no-match message or "try later". The admin reason is under Catalog → Credit Score Checks.

## 12. Configuration

**Environment:** the same Surepass account as CRIF. Equifax may have its own token; without one it uses CRIF's.

| Variable | Default |
| --- | --- |
| `SUREPASS_BASE_URL` | `https://kyc-api.surepass.app` |
| `SUREPASS_BEARER_TOKEN` | secret, server only |
| `SUREPASS_EQUIFAX_TOKEN` / `SUREPASS_EQUIFAX_BASE_URL` | optional, Equifax only; blank = the two above |
| `SUREPASS_TIMEOUT` / `SUREPASS_CONNECT_TIMEOUT` | 60 / 10 seconds |
| `SUREPASS_EQUIFAX_PUBLIC_SCORE_SOURCE` | `report` (`score` once Surepass enables it) |

**Equifax Settings** (admin):

| Setting | Default |
| --- | --- |
| Equifax requests enabled | off |
| Free Equifax Score page uses real scores | off |
| Score reuse period | 30 days |
| Report reuse period | 30 days |
| Consent validity | 30 days |
| Retention | 365 days (never shorter than either reuse period) |
| Alert email | blank (no alerts) |

## 13. Deploying (plain PHP server)

The standard deploy block (`git pull`, `composer install`, `npm run build`, `php artisan migrate --force`, `optimize:clear` / `optimize` / `filament:optimize`, `permission:cache-reset`, `queue:restart`) covers everything. Then:

1. `php artisan equifax:check-access`: the full-report endpoint must show **Allowed**.
2. Under **Equifax → Equifax Settings**, switch on what you need, and set the alert email.
3. Make sure the queue worker and the scheduler cron run. `equifax:flag-stale` runs every 5 minutes; `equifax:enforce-retention` runs daily at 02:45.

## 14. Operations

- **Reconciliation required:** look the call up in the Surepass usage log, then use **Reconcile**. *Not processed* makes the attempt retryable; *processed and charged* closes it.
- **"API not enabled on the Surepass account":** the plan lacks that endpoint. Ask Surepass support, or use a full report.
- **Stuck requests:** `php artisan equifax:flag-stale`.
- **Logs:** request id, product, masked PAN, outcome code and HTTP status. Never the PAN, name, mobile, response or token.

## 15. Tests

Every Surepass call in the suite goes through `Http::fake()` with `Http::preventStrayRequests()` (`fakeSurepassEquifax()` in `tests/Pest.php`). The fixture `tests/Fixtures/Surepass/equifax-report-success.json` is fabricated in the documented credit-report-v2 shape.

```
php artisan test --compact tests/Feature/Equifax tests/Feature/Filament/EquifaxAdminTest.php tests/Feature/CreditScore/LiveEquifaxScoreCheckTest.php
```

**Verify after the first live report:** the parser was built from Surepass's documented structure and a sample (a real customer report was not available to the developer). Compare the first report screen with its raw JSON.
