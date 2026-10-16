# CIBIL credit bureau (Surepass)

How FynnEdge staff, internal modules and the public site get CIBIL (TransUnion CIBIL) credit scores, full reports and PDF reports through Surepass. This covers the module's parts, its API, how paid pulls are kept from being duplicated, configuration, deployment and operations.

> **The one rule.** Every CIBIL pull (score, report or PDF) is a **paid** Surepass call. Nothing here retries one automatically. A request whose outcome is uncertain (timeout, server error, a success without the expected payload) waits for an admin to **reconcile** it against the Surepass dashboard.

---

## 1. What the Surepass plan includes

Checked on 16 Oct 2026 with empty, non-billable requests. Surepass rejects them at input validation or at the token check, so no PAN is sent. The check used the account's shared token, because no `SUREPASS_CIBIL_TOKEN` was set yet. Repeat it with `php artisan cibil:check-access`, or **CIBIL Settings → Check Surepass access**.

| Surepass endpoint | Plan | Used for |
| --- | --- | --- |
| `POST /api/v1/credit-report-cibil/fetch-report` | **Allowed** | Full reports (admin, API, other modules); the public CIBIL page when CIBIL answers it |
| `POST /api/v1/credit-report-cibil/score` | **Not enabled** (401 "not valid for this API/Scope") | Score-only requests |
| `POST /api/v1/credit-report-cibil/fetch-report-pdf` | **Not enabled** (same) | PDF reports |

Until Surepass enables the other two:
- A **score-only** or **PDF** request fails with *API not enabled on the Surepass account*, and nothing is charged. A recent full report still answers a score request.
- The public page reads the score from the full report (`SUREPASS_CIBIL_PUBLIC_SCORE_ENDPOINT=report`, the default).

**Surepass's input validation (live, 16 Oct 2026):**
- `name`, `mobile` (10 digits, not starting with 0), `gender` and `consent` are required; `pan` must be 10 characters.
- `gender` accepts exactly `male` or `female`. "Male", "other" and "transgender" are rejected.
- `consent` accepts `Y` or `N`. FynnEdge sends `Y` only on a recorded consent (§5).

## 2. What existed, and what changed

Before this work, CIBIL had:
- **The public page switch:** the `Cibil` module's `CibilSettings`, with a stand-in source that answered the public page with the visitor's **Experian** score, labelled as Experian.
- **A one-switch settings page:** CIBIL Settings.
- **One permission:** `View:CibilSettings`.

There was no CIBIL provider, table, API or request screen. All of that is kept and extended:
- The stand-in stays available, and **stays the default** (`CIBIL_PUBLIC_SCORE_SOURCE=experian`), so deploying does not change the live page. The real CIBIL bureau is the new `cibil` source.
- CIBIL Settings gains the same settings sections as the other bureaus. Its existing switch, "Free CIBIL Score page shows real scores", keeps its setting key.

## 3. Architecture

CIBIL is its own module, `app/Modules/Cibil/`. It shares no records, services, controllers or admin screens with CRIF, Equifax or Experian.

| Component | Responsibility |
| --- | --- |
| `Actions/RequestCibilCheck` | The only entry point for a paid pull. Checks permission, the on/off switch, the details (PAN, name, mobile, gender) and consent. Then, under a per-PAN lock: block when an equivalent request is in flight, reuse within the product's reuse period, refuse to resend rejected details, otherwise create a pending row and queue the job. |
| `Jobs/FetchCibilRequest` | Queued, `$tries = 1`. `failed()` resolves a job that died. |
| `Actions/ProcessCibilRequest` | Claims the row with a conditional `pending → processing` UPDATE (exactly one paid call per row) and stores the success, failure or reconciliation outcome. For a PDF, it stores the inline Base64 PDF or downloads the linked one (without the token) into encrypted private storage. |
| `Actions/ReconcileCibilRequest` | Records an admin's finding for an uncertain attempt and releases the lock. |
| `Actions/FetchCibilPdf` | Serves a PDF request's stored copy, or tries its Surepass link once more. **Never** calls a paid endpoint. |
| `Actions/RecordWebsiteCibilCheck` | Records each paid public-page check as a CIBIL request (channel `website`). |
| `Services/SurepassCibilService` | The three `credit-report-cibil` operations; classifies every answer (§8). |
| `Services/CibilReportDocument` | Read-only parser for TransUnion's report (§4). |
| `Services/CibilCreditService` | **The reusable application service for other modules** (§10). |
| `Services/CibilSettings`, `CibilRoles`, `CibilFingerprint` | Settings and the CIBIL-only Surepass client; permissions; HMAC keys for request details and in-flight locks. |
| `Models/CibilCreditRequest` | One row per paid attempt. Sensitive columns are encrypted, hidden from serialisation and kept out of the audit trail. |
| `Policies/CibilCreditRequestPolicy` | One policy for the admin, the API and the service. |
| `Providers/SurepassCibilScoreProvider`, `Providers/ExperianCibilScoreProvider` | The public CIBIL page's two sources (§12). |
| `app/Http/Controllers/Api/V1/CibilController` + `routes/cibil.php` | The internal JSON API (§9). |
| `app/Filament/Resources/CibilCreditRequests`, `Pages/Cibil*` | The CIBIL admin section (§11). |

**Shared infrastructure** (not bureau logic):
- `SurepassClient` (transport, no retries). CIBIL builds it with `useSharedCredentials: false`, so it never falls back to the shared or another bureau's token.
- `CreditConsentGate` and `CreditConsentInput`, `CreditConsentFields`, `PanNumber`, `MobileNumber`.
- `audit_logs`, `Setting`, the Credit Officer role, and `ForceJsonResponse`.

## 4. The CIBIL report

`data.credit_report` is a **list** of TransUnion records. Each record holds `control_number`, `names`, `ids`, `telephones`, `emails`, `employment`, `scores` (with `reasonCodes`), `addresses`, `accounts`, `enquiries` and `response.consumerSummaryresp` (`accountSummary`, `inquirySummary`).

`CibilReportDocument` handles it as follows:
- **Multiple records:** reads lists across **every** record, and the summary from the first record that has one.
- **Odd or missing data:** copes with a missing, `null` or empty block, a single object where a list is expected, and unknown fields. Unknown fields stay visible as "other fields" or "other report sections".
- **Masking:** masks ID numbers, phone numbers and account numbers on screen and in the API. Only the raw JSON (ViewRaw) is unmasked.
- **Codes:** labels known codes (ID types, phone types, address categories, ownership, account types) and always shows the code beside the label.
- **Payment history:** decodes `paymentHistory` (three characters per month, newest first, from `paymentStartDate`) into month badges. Days past due above 0, or SMA/SUB/DBT/LSS, is marked late.
- **Dates:** reads `DDMMYYYY` (TransUnion), `YYYYMMDD` and ISO forms.
- **Scores:**
  - Only **300–900** counts as a CIBIL score; zero padding such as `00750` is accepted.
  - `-1`, `000-1`, `1`–`5` (no or too little history), blank or text are stored as **no score**, never coerced.
  - The top-level `credit_score` is preferred; otherwise the first score block's value is used.
- **Summary and insights:** gives a stored `summary()`, and the same `insights()` every bureau document gives to the public result page (counts and totals only).

## 5. Data model: `cibil_credit_requests`

There is one row per paid attempt, for any product, and history is never overwritten: a fresh pull adds a row. There is no uniqueness on PAN or customer, only on the in-flight key. Audit events go to the shared `audit_logs` table.

Key columns:
- **Identity and links:** `public_id` (UUID; the only id in URLs and the API), `customer_id`, `journey_session_id` (the loan application), `credit_consent_id`, `previous_request_id`, `requested_by`, `channel` (`admin` / `api` / `website`).
- **Request:** `product` (`score` | `report` | `pdf`), `request_type` (`standard` | `retry` | `fresh`), `fresh_reason`, `status`.
- **Duplicate protection:** `pan_hash` (HMAC), `in_flight_key` (unique; HMAC of product + PAN while in flight, else NULL), `request_fingerprint` (PAN, name, mobile, gender).
- **Customer details:** `pan_masked`, `mobile_masked`, `gender`, plus `pan_encrypted`, `full_name` and `mobile` (all encrypted).
- **Result:** `score`, `provider_client_id` (Surepass's `client_id`), `provider_report_id` (CIBIL control number), `provider_message_code`, `http_status`, `report_date`, `raw_response` (complete response, encrypted; an inline Base64 PDF is replaced by a marker, because the PDF is kept as a file), `summary` (encrypted).
- **Errors and reconciliation:** `error_code`, `error_message` (safe text), `retryable`, `times_reused`, `duplicates_blocked`, `reconciliation_*`.
- **PDF and timing:** `pdf_path` (encrypted file on the private `local` disk), `pdf_fetched_at`, `pdf_error_code`, `requested_at`, `processing_started_at`, `completed_at`, `purged_at`.

**Migrations:**
- `2026_10_16_124653_create_cibil_credit_requests_table`: new table, reversible.
- `2026_10_16_124654_grant_cibil_request_permissions`: `CibilRoles::grant()`, additive.

No other bureau's table is changed. Enquiries (`contact_enquiries`) are not linked to customers anywhere in the application, so, as with the other bureaus, CIBIL links a result to the **customer** and the **loan application**.

## 6. Consent

Every pull needs one of:
- an existing consent for this customer (the website journey checkbox, or one recorded earlier), with a matching PAN if the consent names one; or
- consent recorded now: method (OTP, signed form, recorded call, email), timestamp, evidence reference and an attestation.

The consent must be *Given* and no older than **Consent validity (days)**. `"consent": "Y"` can only be sent through a `CibilSubject`, which cannot be built without a Given consent. A public-page check records its own consent: method OTP, with the score check's id as evidence.

## 7. Duplicate prevention and reuse

Checked in this order, under a per-PAN lock, inside a transaction:
1. **Equivalent request in flight → BLOCKED.** Any product in flight blocks a score request; a report or PDF is blocked only by one of its own kind. The unique `in_flight_key` index is the final guarantee against simultaneous inserts.
2. **Reusable result → REUSED**, with no call:
   - a report request is answered only by a full report inside the **report reuse period**;
   - a PDF request only by a PDF whose file is still stored;
   - a score request by any of the three inside the **score reuse period**.
   - CRIF, Equifax and Experian results are never used.
3. **Same details already rejected → refused.** Corrected details are a new request.
4. **Otherwise → QUEUED:** one paid call.

**Fetch fresh** skips step 2 only. It needs `FetchFresh:CibilCreditRequest` and a reason of at least 15 characters. **Retry** is allowed once, only for a failure Surepass did not process. The reuse periods default to 30 days, as for the other bureaus, and are editable in CIBIL Settings.

## 8. How Surepass answers are classified

HTTP 200 alone is never success: Surepass must say `success: true` and send the payload asked for.

| Answer | Code | Outcome |
| --- | --- | --- |
| 2xx, `success: true`, expected payload | — | success (a missing `client_id` is stored as empty, never invented) |
| 2xx `success: true` with an empty `credit_report` list / no score / no usable PDF (neither an https link nor Base64 that decodes to a PDF) | `missing_report_payload` / `missing_score` / `missing_pdf_link` | reconciliation |
| 2xx not JSON, or no `success` flag | `malformed_response` | reconciliation |
| 2xx `success: false` | `provider_rejected` / `no_record_found` / `insufficient_balance` | failed |
| 401/403 "not valid for this API/Scope" | `api_not_enabled` | failed, alert, retryable |
| other 401/403 | `authentication_failed` | failed, alert, retryable |
| 402 · 429 · 503 | `insufficient_balance` · `rate_limited` · `provider_unavailable` | failed, retryable |
| 400 / 422 | `invalid_request` / `no_record_found` | failed |
| 500 / 502 / 504 | `provider_error` | reconciliation |
| read timeout | `timeout` | reconciliation |
| DNS, refused, TLS or connect timeout | `connection_failed` | failed, retryable |
| no `SUREPASS_CIBIL_TOKEN` | `not_configured` | nothing sent |

## 9. Internal API (`routes/cibil.php`)

- **Authentication:** the existing staff login (session, `web` group, CSRF on POST). There is no anonymous endpoint. Every call is authorised by the CIBIL policy **and** the customer's own policy (an IDOR guard).
- **Rate limit:** 30 calls a minute per employee (`throttle:cibil-api`).
- **Envelope:** `{ success, message, data | error_code }`.
- **Status codes:** 202 queued, 200 reused or read, 409 in flight, 422 validation or refused, 403, 401 (JSON, never a redirect), 404, 429.

| Method & path | Purpose | Permission |
| --- | --- | --- |
| `POST /api/v1/cibil/score` | Request a score | `Create` + `View:Customer` |
| `POST /api/v1/cibil/reports` | Request a full report | `Create` + `View:Customer` |
| `GET /api/v1/cibil/reports/{id}` | A request. `?include=report` adds the masked report (ViewReport); `?include=raw` adds the raw response (ViewRaw). Both are audited. | `View` + `View:Customer` |
| `POST /api/v1/cibil/reports/{id}/pdf` | The PDF as `application/pdf` when one is stored (or reusable). Otherwise starts one paid PDF request (202) or reports one in progress (409). An expired link gives 422, never a silent re-purchase. | `DownloadPdf` (+ `Create` to start one) |
| `GET /api/v1/cibil/requests/{id}/status` | Status, score, `pdf_available`, safe error | `View` + `View:Customer` |
| `GET /api/v1/cibil/customers/{customer}/reports` | The customer's requests (`per_page` ≤ 50) | `ViewAny` + `View:Customer` |

Request body (`score` and `reports`):
```json
{
  "customer_id": "9b2c…-uuid",
  "pan": "ABCDE1234F",
  "gender": "male",
  "name": "optional — defaults to the customer's name",
  "mobile": "optional — defaults to the customer's phone",
  "journey_session_id": "optional application uuid (must belong to the customer)",
  "consent": { "method": "recorded_call", "evidence_reference": "CALL-1234", "consented_at": "2026-10-16T09:30:00+05:30", "attested": true },
  "fresh": false
}
```

Responses never include the full PAN or mobile, the token, Surepass's error text, the stored PDF path, the temporary PDF link or an inline PDF.

## 10. Reuse from other modules

```php
$cibil = app(\App\Modules\Cibil\Services\CibilCreditService::class);

$cibil->latestScore($customer); // ['score' => 750, 'product' => 'report', 'fetched_at' => …, 'reusable' => true, 'request_id' => …] | null
$cibil->summary($customer);     // headline figures of the newest full report | null
$cibil->request(new CibilCheckRequest(...), $user); // the only call that can spend money
```

The read methods never call Surepass, and opening a page never pulls anything. CIBIL scores are kept apart from the other bureaus' scores and are never interchangeable with them.

## 11. Admin (Filament): the **CIBIL** navigation group

| Menu item | What it is |
| --- | --- |
| CIBIL Dashboard | Score / report / PDF counts, successful, failed, in progress, needs reconciliation, duplicates prevented, results reused, fresh requests, latest requests, per-employee table (website checks as "Website visitors"), provider errors, recent activity |
| CIBIL Credit Reports · Score Requests · PDF Reports | Tabs of the request list |
| CIBIL Requests | Every attempt. Filters: customer, PAN (matched by hash, never in the URL), status, type, request, channel, employee, date range. Searchable by customer, client id and control number. |
| CIBIL Failed Requests | Failed tab (badge = awaiting reconciliation) |
| CIBIL Settings | Request switch, public-page switch and source, reuse periods, consent validity, retention, alert email; token status (never the token); **Check Surepass access** (free) |
| CIBIL Audit History | Every CIBIL event from `audit_logs` (needs `View:AuditLog`) |

The **request page** has two levels:
- **Everyone who can view it:** the overview (masked PAN, gender, client id, control number, PDF status, consent), the score with its score blocks and reason codes, and processing history.
- **With `ViewReport`:** the full report: records, personal information, identities, addresses, phones, emails, employment, account and enquiry summaries, accounts with payment history, enquiries and other sections.
- **Actions:** Download PDF, Get PDF report, raw JSON (ViewRaw), retry, reconcile, fetch fresh.

**Customers and Applications** get a **CIBIL** menu and tab, and the customer list gains a CIBIL column.

**Permissions** (suffix `:CibilCreditRequest`):

| Permission | Granted to Credit Officer? |
| --- | --- |
| `ViewAny`, `View`, `ViewReport`, `Create`, `DownloadPdf`, plus `View:CibilDashboard` | Yes |
| `FetchFresh`, `Reconcile`, `ViewRaw`, plus `View:CibilSettings` | No, grant deliberately |

## 12. The public Free CIBIL Score page

**CIBIL Settings → "Free CIBIL Score page shows real scores"** is off by default, which shows a labelled demo score. When it's on, the page is answered by the source named in `CIBIL_PUBLIC_SCORE_SOURCE`:

| Source | What the visitor sees |
| --- | --- |
| `experian` (default, the original stand-in) | Their real **Experian** score, labelled "Live score from Experian / Your Experian score", with a note that CIBIL isn't connected. Recorded in the Experian admin. |
| `cibil` | Their genuine **CIBIL** score: "Live score from CIBIL / Your CIBIL score". The page asks for **gender** (CIBIL requires it). Recorded in the CIBIL admin as a `website` request, with consent and the full report. |

With either source:
- **No repeat charges:** the same PAN and mobile checking again within the reuse period sees the stored score at no cost, and an uncertain answer is held for an hour.
- **No fake scores:** a failed check never shows a demo score.
- **No token borrowing:** without `SUREPASS_CIBIL_TOKEN`, the `cibil` source counts as not configured and the page stays a labelled demo. It never borrows another bureau's token.

## 13. Configuration

| Variable | Default |
| --- | --- |
| `SUREPASS_CIBIL_TOKEN` | secret, server only, **required**; no fallback |
| `SUREPASS_CIBIL_BASE_URL` | `https://kyc-api.surepass.app` |
| `SUREPASS_CIBIL_PUBLIC_SCORE_ENDPOINT` | `report` (`score` once Surepass enables it) |
| `CIBIL_PUBLIC_SCORE_SOURCE` | `experian` (set `cibil` to use the CIBIL bureau) |
| `SUREPASS_TIMEOUT` / `SUREPASS_CONNECT_TIMEOUT` | 60 / 10 seconds (shared) |

**CIBIL Settings** (stored in the shared `settings` table):
- CIBIL requests enabled: off by default.
- Public page shows real scores: off by default.
- Score and report reuse periods: 30 days each.
- Consent validity: 30 days.
- Retention: 365 days.
- Alert email: blank.

## 14. Deploying (plain PHP server), once approved

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force          # adds cibil_credit_requests + CIBIL permissions; nothing is dropped
php artisan optimize:clear && php artisan optimize && php artisan filament:optimize
php artisan permission:cache-reset
php artisan queue:restart
```

Then:
1. Set `SUREPASS_CIBIL_TOKEN` in the server's `.env`, then run `php artisan optimize` again.
2. Run `php artisan cibil:check-access`. "Token loaded" must say `yes (SUREPASS_CIBIL_TOKEN)`, and fetch-report must show **Allowed**.
3. Under **CIBIL → CIBIL Settings**, switch on what you need and set the alert email. Nothing is enabled automatically.
4. To have the public CIBIL page show genuine CIBIL scores, set `CIBIL_PUBLIC_SCORE_SOURCE=cibil` and run `php artisan optimize`.
5. Make sure the queue worker and the scheduler cron run. Staff and API requests are queued; the public page is not. `cibil:flag-stale` runs every 5 minutes, and `cibil:enforce-retention` runs daily at 02:55.

## 15. Operations

- **Reconciliation required:** look the call up in the Surepass usage log (by time and `client_id`), then use **Reconcile**.
- **"API not enabled on the Surepass account":** the plan lacks that endpoint (today: score and PDF). Ask Surepass support.
- **"Integration not configured":** `SUREPASS_CIBIL_TOKEN` is not set, or CIBIL requests are switched off.
- **Logs:** request id, product, masked PAN, outcome code and HTTP status. Never the PAN, name, mobile, response, PDF link, Base64 PDF or token.

## 16. Tests

Every Surepass call in the suite goes through `Http::fake()` with `Http::preventStrayRequests()` (`fakeSurepassCibil()` in `tests/Pest.php`, with a fake CIBIL-only token). The fixtures `tests/Fixtures/Surepass/cibil-{score,report,pdf}-success.json` are fabricated.

```
php artisan test --compact tests/Feature/Cibil tests/Feature/Filament/CibilAdminTest.php tests/Feature/CreditScore/LiveCibilBureauScoreCheckTest.php tests/Feature/CreditScore/LiveCibilScoreCheckTest.php
```

**Verify after the first live report:** the parser follows Surepass's documented example and TransUnion's published field names; no real CIBIL response was available. Compare the first report screen with its raw JSON, especially code labels and dates.
