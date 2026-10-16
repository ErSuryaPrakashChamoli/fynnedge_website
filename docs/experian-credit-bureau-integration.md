# Experian credit bureau (Surepass)

How FynnEdge staff, internal modules and the public site get Experian credit scores, full reports and PDF reports through Surepass. This covers the module's parts, its API, how paid pulls are kept from being duplicated, configuration, deployment and operations.

> **The one rule.** Every Experian pull — score, report or PDF — is a **paid** Surepass call. Nothing here retries one automatically. A request whose outcome is uncertain (timeout, server error, a success without the expected payload) waits for an admin to **reconcile** it against the Surepass dashboard.

---

## 1. What the Surepass plan includes

Checked on 16 Oct 2026 with empty, non-billable requests (Surepass rejects them at input validation or at the token check — no PAN is sent). Repeat any time with `php artisan experian:check-access`, or **Experian Settings → Check Surepass access**.

| Surepass endpoint | Plan | Used for |
| --- | --- | --- |
| `POST /api/v1/credit-report-experian/fetch-report` | **Allowed** | Full reports (admin, API, other modules); the public score page |
| `POST /api/v1/credit-report-experian/score` | **Not enabled** (401 "not valid for this API/Scope") | Score-only requests |
| `POST /api/v1/credit-report-experian/fetch-report-pdf` | **Not enabled** (same) | PDF reports |

Consequences until Surepass enables the other two:
- A **score-only** request fails with *API not enabled on the Surepass account*, and nothing is charged, unless a recent report or PDF can answer it (§6). Request a full report instead; it carries the score.
- A **PDF** request fails the same way, and nothing is charged.
- The **public Free Experian Score page** reads the score from the full report (`SUREPASS_EXPERIAN_PUBLIC_SCORE_SOURCE=report`, the default).

Surepass's input validation for Experian requires `name`, `mobile` (10 digits, not starting with 0) and `pan` (10 characters). It asks for no gender and no id type, and it does not validate `consent`. FynnEdge still sends `"consent": "Y"`, and only on the strength of a recorded consent (§5).

## 2. Architecture

Experian is its own module, `app/Modules/Experian/`. It shares no records, services, controllers or admin screens with CRIF or Equifax. CIBIL has no live integration (its public page is a demo) and is untouched.

| Component | Responsibility |
| --- | --- |
| `Actions/RequestExperianCheck` | The only entry point for a paid pull. Checks permission, the on/off switch, the details (PAN, name, mobile) and consent. Then, under a per-PAN lock: block when an equivalent request is in flight, reuse within the product's reuse period, refuse to resend rejected details, otherwise create a pending row and queue the job. |
| `Jobs/FetchExperianRequest` | Queued, `$tries = 1`. `failed()` resolves a job that died. |
| `Actions/ProcessExperianRequest` | Claims the row with a conditional `pending → processing` UPDATE, so exactly one paid call happens per row. Stores the success, failure or reconciliation outcome. For a PDF, downloads the linked file at once (without the token) into encrypted private storage. |
| `Actions/ReconcileExperianRequest` | Records an admin's finding for an uncertain attempt and releases the lock. |
| `Actions/FetchExperianPdf` | Serves a PDF request's stored copy, or tries its Surepass link once more. **Never** calls a paid endpoint. |
| `Services/SurepassExperianService` | The three `credit-report-experian` operations; classifies every answer (§7). |
| `Services/ExperianReportDocument` | Read-only parser for Experian's report (CreditProfileHeader, Current_Application, CAIS_Account, Match_result, TotalCAPS_Summary, CAPS, NonCreditCAPS, SCORE). It finds sections by name, copes with an object, a list or `""` in any block, masks identifiers on screen, and never drops a field. |
| `Services/ExperianCreditService` | **The reusable application service for other modules** (§9). |
| `Services/ExperianSettings`, `ExperianRoles`, `ExperianFingerprint` | Admin settings and the Surepass client for Experian; permissions; HMAC keys for request details and in-flight locks. |
| `Models/ExperianCreditRequest` | One row per paid attempt. Sensitive columns are encrypted, hidden from serialisation and kept out of the audit trail. |
| `Policies/ExperianCreditRequestPolicy` | One policy for the admin, the API and the service. |
| `app/Http/Controllers/Api/V1/ExperianController` + `routes/experian.php` | The internal JSON API (§8). |
| `app/Filament/Resources/ExperianCreditRequests`, `Pages/Experian*` | The Experian admin section (§10). |
| `app/Modules/CreditScore/Providers/SurepassExperianScoreProvider` | Real scores on the public Experian page (§11). |

**Shared with CRIF and Equifax, deliberately** (common infrastructure, not bureau logic):
- `CreditBureau/Services/SurepassClient`: the Surepass transport (timeouts, no retries, connection-failure handling, PDF download without the token, access probe). Experian gets its own instance with its own token when one is set (`ExperianSettings::surepassClient()`, bound for `SurepassExperianService` in `AppServiceProvider`).
- `CreditBureau/Services/CreditConsentGate` + `CreditConsentInput`: consent validation and recording against the existing `credit_consents` table.
- `App\Filament\Schemas\CreditConsentFields`: the consent part of request forms.
- `PanNumber`, `MobileNumber`, `audit_logs` (`Auditable`), `Setting`, Shield roles (the **Credit Officer** role), the `ForceJsonResponse` middleware.

## 3. Data model: `experian_credit_requests`

There is one row per paid attempt, for any product, and history is never overwritten: a fresh pull adds a row. There is no uniqueness on PAN or customer, only on the in-flight key below. This follows the CRIF and Equifax single-table design rather than separate request and report tables. Audit events go to the shared `audit_logs` table (one audit system).

Key columns:
- **Identity and links:** `public_id` (UUID; the only id in URLs and the API), `customer_id`, `journey_session_id` (the loan application), `credit_consent_id`, `previous_request_id` (retry or fresh chain), `requested_by`, `channel` (`admin` / `api`).
- **Request:** `product` (`score` | `report` | `pdf`), `request_type` (`standard` | `retry` | `fresh`), `fresh_reason`, `status`.
- **Duplicate protection:** `pan_hash` (HMAC), `in_flight_key` (unique; HMAC of product + PAN while in flight, else NULL), `request_fingerprint`.
- **Customer details:** `pan_masked`, `mobile_masked`, plus `pan_encrypted`, `full_name` and `mobile` (all encrypted).
- **Result:** `score`, `provider_client_id` (Surepass's `client_id`, the reference for reconciliation), `provider_report_id` (Experian's ReportNumber), `provider_message_code`, `http_status`, `report_date`, `raw_response` (complete response, encrypted), `summary` (derived headline figures, encrypted, separate from the original).
- **Errors and reconciliation:** `error_code`, `error_message` (safe text), `retryable`, `times_reused`, `duplicates_blocked`, `reconciliation_*`.
- **PDF and timing:** `pdf_path` (encrypted file on the private `local` disk), `pdf_fetched_at`, `pdf_error_code`, `requested_at`, `processing_started_at`, `completed_at`, `purged_at`.

Indexes: in-flight key (unique), PAN + product + status + date, status + created, requested_at, client id, report number, customer + product + date (history).

Migrations: `2026_10_16_074730_create_experian_credit_requests_table` (reversible) and `2026_10_16_074732_create_experian_permissions` (`ExperianRoles::grant()`; additive, and rollback deliberately leaves permissions in place). No CRIF, Equifax or CIBIL table is changed.

**Lead:** enquiries (`contact_enquiries`) are not linked to customers anywhere in the application, so, as with CRIF and Equifax, Experian links a result to the **customer** and the **loan application** (`journey_session_id`).

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

The consent must be *Given* and no older than **Consent validity (days)**. `"consent": "Y"` can only be sent through an `ExperianSubject`, which cannot be built without a Given consent. A PDF asked for from an earlier report reuses that report's consent, and the consent is re-checked for validity.

## 6. Duplicate prevention and reuse

Checked in this order, under a per-PAN lock, inside a transaction:

1. **Equivalent request in flight → BLOCKED** (`duplicates_blocked` + 1). Any product in flight blocks a score request (reports and PDFs carry the score). A report or PDF is blocked only by one of its own kind.
2. **Reusable result → REUSED** (`times_reused` + 1), no call:
   - a **report** request is answered only by a full report inside the **report reuse period**;
   - a **PDF** request is answered only by a PDF whose file is still stored, inside the report reuse period;
   - a **score** request is answered by a score, report or PDF inside the **score reuse period**;
   - a score never answers a report or PDF request;
   - CRIF, Equifax and CIBIL results are never used.
3. **Same details already rejected** (same PAN, name and mobile fingerprint) → refused. Corrected details are a new request.
4. Otherwise → **QUEUED**: one paid call.

The unique index on `in_flight_key` is the final guarantee. A simultaneous insert that slipped past step 1 is refused by the database and answered as BLOCKED.

**Fetch fresh** skips step 2 only. It needs `FetchFresh:ExperianCreditRequest` and a reason of at least 15 characters, which is stored and audited. **Retry** is allowed once, only for a failure Surepass did not process. A PAN is never blocked permanently: once a reuse period ends, a normal request pulls again. The reuse periods are the existing ones (30 days, as for CRIF and Equifax) and are editable in Experian Settings.

## 7. How Surepass answers are classified

HTTP 200 alone is never success: Surepass must say `success: true` and send the payload asked for.

| Answer | Code | Outcome |
| --- | --- | --- |
| 2xx, `success: true`, expected payload | — | success (a missing `client_id` is stored as empty, never invented) |
| 2xx `success: true` without report / score / usable https PDF link | `missing_report_payload` / `missing_score` / `missing_pdf_link` | reconciliation |
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

## 8. Internal API (`routes/experian.php`)

- **Authentication:** the application's existing staff login (session, `web` group, with CSRF on POST). The app has no API tokens (no Sanctum), and there is no anonymous endpoint. Every call is authorised by the Experian policy *and* the customer's own policy, so a guessed or leaked id is useless outside that boundary.
- **Rate limit:** 30 calls a minute per employee (`throttle:experian-api`), separate from Equifax's.
- **Envelope:** `{ success, message, data | error_code }`.
- **Status codes:**
  - 202 queued; 200 reused or read; 409 an equivalent request is in flight;
  - 422 validation failed or request refused; 403 not permitted; 401 signed out (JSON, never a redirect);
  - 404 unknown id; 429 rate limited.
- **Inside the application:** modules should call `ExperianCreditService` instead of making HTTP requests to their own API.

| Method & path | Purpose | Permission |
| --- | --- | --- |
| `POST /api/v1/experian/score` | Request a score (may reuse / block / queue) | `Create` + `View:Customer` |
| `POST /api/v1/experian/reports` | Request a full report | `Create` + `View:Customer` |
| `GET /api/v1/experian/reports/{id}` | A request. `?include=report` adds the full report (masked; needs `ViewReport`); `?include=raw` adds the unmasked response (needs `ViewRaw`). Both are audited. | `View` + `View:Customer` |
| `POST /api/v1/experian/reports/{id}/pdf` | The PDF (see below) | `DownloadPdf` + `View` + `View:Customer` (+ `Create` to start a PDF request) |
| `GET /api/v1/experian/requests/{id}/status` | Status, score, `pdf_available`, safe error | `View` + `View:Customer` |
| `GET /api/v1/experian/customers/{customer}/reports` | The customer's requests (paginated, `per_page` ≤ 50) | `ViewAny` + `View:Customer` |

**`POST /reports/{id}/pdf`:**
- Returns `application/pdf` (`Cache-Control: no-store, private`, plus an `X-Experian-Request-Id` header) when `{id}` is a PDF request whose file is stored or still downloadable, or when a recent stored PDF for the same PAN is reused.
- Otherwise it starts a paid PDF request for the same person on the record's consent. It answers `202` with that request (poll its status, then call this endpoint with **its** id), or `409` if one is already in progress.
- A PDF whose link has expired answers `422`. It is never re-bought silently; request a new PDF instead.

Request body (`score` and `reports`):
```json
{
  "customer_id": "9b2c…-uuid",
  "journey_session_id": "optional application uuid (must belong to the customer)",
  "pan": "ABCDE1234F",
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
  "message": "Experian report requested. It usually arrives within a minute.",
  "outcome": "queued",
  "data": {
    "id": "…uuid", "customer_id": "…", "application_id": null, "product": "report",
    "request_type": "standard", "status": "pending", "status_label": "Pending", "score": null,
    "pan_masked": "XXXXXX234F", "mobile_masked": "******3210",
    "requested_at": "2026-10-16T09:31:02+00:00", "completed_at": null, "report_date": null,
    "reusable": false, "reusable_until": null, "provider_client_id": null, "report_number": null,
    "error": null, "pdf_stored": false, "pdf_available": false
  }
}
```

The response never includes the full PAN or mobile, the token, Surepass's error text, the stored PDF path, or the temporary PDF link.

## 9. Reuse from other modules

```php
$experian = app(\App\Modules\Experian\Services\ExperianCreditService::class);

$experian->latestScore($customer); // ['score' => 792, 'product' => 'report', 'fetched_at' => …, 'reusable' => true, 'request_id' => …] | null
$experian->summary($customer);     // headline figures of the newest full report | null
$experian->latest($customer, ExperianProduct::Pdf, reusableOnly: true);
$experian->request(new ExperianCheckRequest(...), $user); // the only call that can spend money
```

The read methods never call Surepass, so they are safe on every page load. Opening a customer or report page never pulls anything. `request()` applies the same permission, consent, duplicate and reuse rules as the admin. Experian scores are kept apart from other bureaus' scores and are never interchangeable with them.

## 10. Admin (Filament): the **Experian** navigation group

| Menu item | What it is |
| --- | --- |
| Experian Dashboard | Score / report / PDF request counts, successful, failed, in progress, needs reconciliation, duplicates prevented, results reused, fresh requests, latest requests, per-employee table, provider errors, recent activity |
| Experian Credit Reports | Full-report tab of the request list |
| Experian Score Requests | Score tab |
| Experian PDF Reports | PDF tab (with a stored / unavailable PDF column) |
| Experian Requests | Every attempt. Tabs: all, full reports, scores, PDF reports, failed, needs reconciliation. Filters: customer, PAN (matched by hash, never in the URL), status, type, request, employee, date range. Searchable by customer, client id and report number. |
| Experian Failed Requests | Failed tab (badge = awaiting reconciliation) |
| Experian Settings | On/off switches, reuse periods, consent validity, retention, alert email. Shows where the token is read from (never the token) and the endpoints. **Check Surepass access** runs the free probe. |
| Experian Audit History | Every Experian event from `audit_logs` (needs `View:AuditLog`) |

**Request page:**
- Shown to anyone who may view the request: overview (masked PAN, client id, report number, PDF status, consent), the score with Experian's confidence level, and processing history.
- With `ViewReport`, the full report: report header, personal information, identities (masked), the application Experian scored, addresses, phones (masked), emails, account and enquiry summaries, accounts (labelled account type, open/closed, amounts, monthly days-past-due history, every other field with identifiers masked), credit and non-credit enquiries, and every other section.
- Without it: score and status only.
- Actions: **Download PDF** (PDF requests; served from the stored copy), **Get PDF report** (from a score or report: a new paid PDF request, answered by a recent stored PDF when one exists), raw JSON (`ViewRaw`), retry, reconcile, fresh pull. The History tab is the audit log.

**Customers and Applications** get an **Experian** menu (view latest, fetch report, check score, get PDF report, fetch fresh) and an **Experian** tab. The customer list also gains an Experian status column.

### Permissions

| Permission | Credit Officer |
| --- | --- |
| `ViewAny`, `View`, `ViewReport`, `Create`, `DownloadPdf` `:ExperianCreditRequest`; `View:ExperianDashboard` | ✔ |
| `FetchFresh`, `Reconcile`, `ViewRaw` `:ExperianCreditRequest`; `View:ExperianSettings` | — (grant deliberately) |
| `View:AuditLog` (existing) | — |

## 11. Public Free Experian Score page (`/credit-score/experian`)

**Experian Settings → "Free Experian Score page uses real scores"** (off by default) switches the page from the demo score to real scores:
- The visitor's details (OTP-verified mobile, name, PAN, consent tick) go to `credit-report-experian/fetch-report`. No gender is asked. Only the score, client id and message code are kept, in `credit_score_checks`; the rest of the report is discarded.
- The same PAN and mobile checking again inside the score reuse period sees the stored score at no cost. Uncertain answers are held for an hour.
- A failed live check is never replaced by a demo score. The visitor sees "Live score from Experian", "No score yet", a no-match message or "try later". The admin reason is under Catalog → Credit Score Checks.
- Switched off, the page shows a demo score that is clearly labelled "Demo score — not your real credit report". The CIBIL page is always a demo.

## 12. Configuration

**Environment** (server only, never committed):

| Variable | Default |
| --- | --- |
| `SUREPASS_EXPERIAN_TOKEN` | optional, Experian only; blank = `SUREPASS_BEARER_TOKEN` |
| `SUREPASS_EXPERIAN_BASE_URL` | optional; blank = `SUREPASS_BASE_URL` (`https://kyc-api.surepass.app`) |
| `SUREPASS_TIMEOUT` / `SUREPASS_CONNECT_TIMEOUT` | 60 / 10 seconds (shared) |
| `SUREPASS_EXPERIAN_PUBLIC_SCORE_SOURCE` | `report` (`score` once Surepass enables it) |

`php artisan experian:check-access` prints which variable the token was read from (never its value).

**Experian Settings** (admin; stored in the shared `settings` table):

| Setting | Default |
| --- | --- |
| Experian requests enabled | off |
| Free Experian Score page uses real scores | off |
| Score reuse period | 30 days |
| Report (and PDF) reuse period | 30 days |
| Consent validity | 30 days |
| Retention | 365 days (never shorter than either reuse period) |
| Alert email | blank (no alerts) |

## 13. Deploying (plain PHP server), once approved

The standard deploy block covers everything:

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force            # adds experian_credit_requests + Experian permissions; nothing is dropped
php artisan optimize:clear && php artisan optimize && php artisan filament:optimize
php artisan permission:cache-reset
php artisan queue:restart
```

Then:
1. Optionally set `SUREPASS_EXPERIAN_TOKEN` in the server's `.env` (otherwise the shared token is used), then `php artisan config:clear` (or `optimize` again).
2. `php artisan experian:check-access`: the full-report endpoint must show **Allowed**.
3. Under **Experian → Experian Settings**, switch on what you need, and set the alert email. Nothing is enabled automatically.
4. Make sure the queue worker (`QUEUE_CONNECTION=database`) and the scheduler cron run. Admin and API requests are queued; the public page is not. `experian:flag-stale` runs every 5 minutes and `experian:enforce-retention` daily at 02:50.

## 14. Operations

- **Reconciliation required:** look the call up in the Surepass usage log (by time and `client_id` where present), then use **Reconcile**. *Not processed* makes the attempt retryable; *processed and charged* closes it.
- **"API not enabled on the Surepass account":** the plan lacks that endpoint (today: score and PDF). Ask Surepass support, or use a full report.
- **PDF "link expired or unreadable":** the PDF request succeeded, but its temporary link could not be downloaded. Request a new PDF if one is still needed.
- **Stuck requests:** `php artisan experian:flag-stale`.
- **Logs:** request id, product, masked PAN, outcome code and HTTP status. Never the PAN, name, mobile, response, PDF link or token.

## 15. Tests

Every Surepass call in the suite goes through `Http::fake()` with `Http::preventStrayRequests()` (`fakeSurepassExperian()` in `tests/Pest.php`). The fixtures in `tests/Fixtures/Surepass/experian-{score,report,pdf}-success.json` are fabricated, synthetic data.

```
php artisan test --compact tests/Feature/Experian tests/Feature/Filament/ExperianAdminTest.php tests/Feature/CreditScore/LiveExperianScoreCheckTest.php
```

**Verify after the first live report:** the parser was built from Surepass's documented example and Experian's published report structure; no real Experian response was available. Compare the first report screen with its raw JSON. In particular, the account-type labels are always shown with their code, and gender (1 = male, 2 = female) is shown only when Experian sends those codes.
