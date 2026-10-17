# Mobile OTP verification (Areach SMS)

How FynnEdge proves a visitor holds a mobile number: what the pieces are, how to call the API, how to run it in production, and what to do when SMS stops arriving.

> **The one rule.** A number is verified **only** when the visitor enters the correct code. An SMS the provider *accepted* is not a verified number, and neither the browser, an admin, nor a provider response can mark one verified.

---

## 1. Architecture

Everything lives in `app/Modules/MobileOtp/`. Every OTP on the site goes through the same two actions.

| Component | Responsibility |
| --- | --- |
| `Actions/RequestMobileOtp` | Rate limits and cooldown (under a per-number lock), supersedes older codes, stores the bcrypt hash, **then** sends the SMS outside any transaction, and records the result. The only place a plaintext code exists. |
| `Actions/VerifyMobileOtp` | Re-reads the challenge under a row lock, enforces expiry, status and attempts, compares with `Hash::check`, and marks it verified. Returns a `MobileOtpVerificationResult`. |
| `Models/MobileOtpChallenge` | One code for one number and one purpose. `findForVerification()` (id + number + purpose), `consumeFor()` (atomic one-time redemption), `effectiveStatus()` (derives *expired*). |
| `Contracts/SmsProvider` | The gateway seam: `name()` and `sendOtp(recipient, message): SmsSendResult`. Must never throw, retry or log the message. |
| `Providers/AreachSmsProvider` | Areach implementation (Laravel HTTP client, no retries). |
| `Providers/LogSmsProvider` | Local development only. Writes the SMS to the log, and refuses to send unless `APP_ENV` is `local` or `testing`. |
| `DataTransferObjects/SmsSendResult` | Normalised provider answer: status, provider reference, failure category, HTTP status. Never raw bodies. |
| `Services/MobileNumber` | The single definition of a mobile number: normalise, validate, mask, international form. `ContactEnquiry::normalizePhone()` delegates here. |
| `Exceptions/MobileOtpThrottled` | Cooldown or rate limit refusal, with `retryAfterSeconds`. Each caller turns it into its own answer. |
| `app/Http/Controllers/Api/V1/MobileOtpController` | The public API (`send`, `verify`). |
| `app/Filament/Resources/MobileOtpChallenges` | Read-only admin log. |
| `app/Console/Commands/MobileOtpSmokeTest` | Controlled single live SMS test. |

**Who calls it**

| Flow | Purpose | Endpoint(s) | Protected action |
| --- | --- | --- | --- |
| Homepage Quick Enquiry box | `loan_enquiry` | `POST /quick-enquiry/otp`, `POST /quick-enquiry` | Lead in `contact_enquiries` with `phone_verified_at` |
| Loan application journey | `loan_application` | `POST /journey/{session}/phone/send-otp`, `.../verify-otp` | `journey_sessions.phone_verified_at`; `JourneyController::update()` refuses any other number |
| Free credit-score check (Livewire) | `credit_score_check` | Livewire actions | `CreditScoreCheck` row |
| Contact page, only when a phone is filled in | `contact_enquiry` | The public API from the page, then `POST /contact` with `otp_challenge_id` | `contact_enquiries` row with `phone_verified_at`; a blank phone needs no code |
| Customer portal sign-in (`/existing-customer`, Livewire) | `customer_login` | Livewire actions | A `customer` guard session (docs/customer-portal.md) |
| Public API | any purpose except `customer_login` | `POST /api/v1/mobile-otp/send`, `/verify` | Redeemable once on `POST /quick-enquiry` (see §15) |

Not OTP-protected (unchanged and deliberate): the loan-page enquiry form and the `/quick-enquiry` page form.

---

## 2. Areach API as integrated

```
POST {AREACH_BASE_URL}/sms
Content-Type: application/json
Accept: application/json
apikey: {AREACH_API_KEY}

{ "sender": "{AREACH_SENDER}", "to": "91XXXXXXXXXX", "text": "<rendered template>", "type": "OTP" }
```

- Timeout is `AREACH_SMS_TIMEOUT` seconds; the connect timeout is the lesser of 5 seconds and that value. There are **no automatic retries**.
- **Response format is not publicly documented**, so it is read conservatively:

| Provider answer | Stored `sms_status` | Challenge |
| --- | --- | --- |
| 2xx JSON with `success: true`, `status: true`, or `status` ∈ success/ok/accepted/submitted/queued/sent | `accepted` (+ reference if an id field is present) | `pending` |
| 2xx JSON with `success: false`, `status` ∈ error/failed/rejected/invalid…, or a non-empty `error`/`errors` | `failed` · `provider_rejected` | `failed` |
| Non-2xx | `failed` · `http_error` (+ HTTP status) | `failed` |
| 2xx but not JSON | `unconfirmed` · `invalid_response` | `pending` |
| 2xx JSON with no recognised marker | `unconfirmed` · `unrecognised_response` | `pending` |
| Timeout (cURL 28) | `unconfirmed` · `timeout` | `pending` |
| DNS or connection failure | `failed` · `connection_failed` | `failed` |
| Key, sender or base URL missing | `failed` · `not_configured` (no request made) | `failed` |

When the live smoke test shows Areach's real response, tighten `acceptsBody()` and `rejectsBody()` in `AreachSmsProvider`. The command prints the response **shape** (keys and types, never values) for exactly this.

**DLT (India).** Operators drop SMS whose text doesn't match the DLT template registered for the sender ID. `MOBILE_OTP_SMS_TEMPLATE` must match the approved template character for character. If Areach requires a DLT template ID or entity ID in the request, that isn't in the example payload we were given, so it isn't sent; confirm with Areach.

---

## 3. Environment variables

| Variable | Default | Notes |
| --- | --- | --- |
| `MOBILE_OTP_SMS_PROVIDER` | `areach` | `areach` or `log` (local only). An unknown value throws instead of falling back. |
| `AREACH_BASE_URL` | `https://api.areach.in/v1` | |
| `AREACH_API_KEY` | none | **Secret.** Server environment only. |
| `AREACH_SENDER` | `FYNNED` | DLT-approved sender ID. |
| `AREACH_SMS_TIMEOUT` | `10` | Seconds. |
| `MOBILE_OTP_LENGTH` | `6` | Clamped to 4–8; forms and validation follow it. |
| `MOBILE_OTP_EXPIRY_MINUTES` | `10` | Also substituted into the SMS as `:minutes`. |
| `MOBILE_OTP_MAX_ATTEMPTS` | `5` | Wrong guesses per challenge; stored on each challenge at issue time. |
| `MOBILE_OTP_RESEND_COOLDOWN_SECONDS` | `60` | Per number + purpose. |
| `MOBILE_OTP_MAX_SENDS_PER_NUMBER_PER_HOUR` | `5` | Across all purposes. |
| `MOBILE_OTP_MAX_SENDS_PER_IP_PER_HOUR` | `20` | Covers Livewire too. |
| `MOBILE_OTP_VERIFIED_TTL_MINUTES` | `30` | How long a verified challenge can still be redeemed. |
| `MOBILE_OTP_SMS_TEMPLATE` | the FynnEdge text | `:code`, `:minutes` placeholders. |
| `MOBILE_OTP_LIVE_TEST_ENABLED` | `false` | Only `true` while running the smoke test. |

Credentials are read through `config('services.areach.*')` and policy through `config('mobile_otp.*')`. Application code never calls `env()`.

---

## 4. Database

Migration `2026_10_13_071135_add_sms_delivery_and_lifecycle_to_mobile_otp_challenges_table` is **additive only**. It adds columns to the existing `mobile_otp_challenges` table, with no new tables and no dropped columns.

| Column | Meaning |
| --- | --- |
| `purpose` | `loan_enquiry` / `loan_application` / `credit_score_check` / `contact_enquiry`. NULL on rows created before this migration. |
| `status` | Challenge lifecycle (§12). Default `pending`. |
| `max_attempts` | Attempt cap captured at issue time. |
| `consumed_at` | When a protected action redeemed the verified challenge. |
| `sms_provider`, `sms_status`, `sms_provider_reference`, `sms_failure_reason`, `sms_http_status`, `sms_sent_at` | Safe send metadata. |

Indexes: `mobile_otp_lookup_index (mobile_number, purpose, created_at)` and `mobile_otp_status_index (status, created_at)`.

The backfill keeps every existing row: `sms_status = not_sent` (they were demo-mode), `status = verified` where `verified_at` is set, and `exhausted` where attempts ≥ 5. Reversible: `down()` drops only the added columns and indexes.

---

## 5. `POST /api/v1/mobile-otp/send`

Public and stateless (`api` middleware group): no session, no CSRF, no authentication. Throttled per IP.

| Field | Rules |
| --- | --- |
| `mobile_number` | required. Normalised first (`+91 98765-43210` → `9876543210`), then must be 10 digits starting 6–9. |
| `country_code` | optional, default `IN`; only `IN` is supported. |
| `purpose` | required; `loan_enquiry` \| `loan_application` \| `credit_score_check` \| `contact_enquiry`. |

```bash
curl --location "$APP_URL/api/v1/mobile-otp/send" \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --data '{
    "mobile_number": "9876543210",
    "country_code": "IN",
    "purpose": "loan_enquiry"
  }'
```

**201 Created**

```json
{
  "data": {
    "challenge_id": "7c0787f8-5295-4d0f-bfd7-57a06767454c",
    "mobile_number_masked": "******3210",
    "purpose": "loan_enquiry",
    "status": "pending",
    "sms_status": "accepted",
    "expires_at": "2026-10-13T07:44:53+00:00",
    "expires_in_seconds": 600,
    "resend_after_seconds": 60,
    "verified": false,
    "verified_at": null
  },
  "success": true,
  "message": "Verification code sent if delivery was accepted."
}
```

`sms_status` is `accepted` or `unconfirmed`, never "delivered". The response never contains the code, its hash, the full number, the API key or anything from the provider's response.

**Failures**

| HTTP | `error_code` | When |
| --- | --- | --- |
| 422 | `validation_failed` | Bad or missing fields. Also has `errors.{field}[]`. |
| 429 | `resend_cooldown` | Inside the cooldown. `data.retry_after_seconds` and a `Retry-After` header. |
| 429 | `too_many_requests` | Hourly per-number or per-IP cap. Same retry fields. |
| 429 | none (Laravel default body) | Route throttle `mobile-otp-send` (5/min, 30/day per IP). |
| 503 | `sms_not_sent` | Provider rejected or unreachable. No challenge id is returned, and the cooldown does not start. |

```json
{ "success": false, "message": "Please wait 40 seconds before requesting another code.", "error_code": "resend_cooldown", "data": { "retry_after_seconds": 40 } }
```

---

## 6. `POST /api/v1/mobile-otp/verify`

| Field | Rules |
| --- | --- |
| `challenge_id` | required UUID from `send`. |
| `mobile_number`, `country_code`, `purpose` | same as `send`; **all must match the challenge**. |
| `otp` | required, exactly `MOBILE_OTP_LENGTH` digits. Send it as a string so leading zeros survive. |

```bash
curl --location "$APP_URL/api/v1/mobile-otp/verify" \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --data '{
    "challenge_id": "'"$CHALLENGE_ID"'",
    "mobile_number": "9876543210",
    "country_code": "IN",
    "purpose": "loan_enquiry",
    "otp": "<OTP>"
  }'
```

**200 OK**: the same `data` shape as `send`, with `"status": "verified"`, `"verified": true`, `"verified_at": "<ISO-8601>"` and `"message": "Mobile number verified successfully."`

**422 failures** (`success: false`)

| `error_code` | Meaning | Extra |
| --- | --- | --- |
| `invalid_otp` | Wrong code, **or** no challenge matches the id + number + purpose | `data.attempts_remaining` only for a wrong code on a matching challenge |
| `otp_expired` | Past `expires_at` | |
| `otp_already_used` | Already verified | |
| `otp_superseded` | A newer code was issued for this number + purpose | |
| `too_many_attempts` | Attempt cap reached; request a new code | |
| `otp_not_sent` | The SMS for this challenge was rejected | |
| `validation_failed` | Bad fields | `errors` |

A mismatched number, mismatched purpose or unknown id returns **exactly** the same body as a wrong code and does not touch the challenge. That way the endpoint never reveals whether someone else's number has an active challenge.

Route throttle `mobile-otp-verify`: 10/min and 60/hour per IP.

---

## 7. HTTP status summary

| Status | Endpoint | Meaning |
| --- | --- | --- |
| 200 | verify | Verified |
| 201 | send | Challenge created, SMS accepted or unconfirmed |
| 422 | both | Validation or verification refusal |
| 429 | both | Cooldown or rate limit |
| 503 | send | SMS not sent |

---

## 8. Rate limiting and cooldown

All of these limits apply together.

1. **Route throttles (per IP)**: `mobile-otp-send` and `mobile-otp-verify` in `AppServiceProvider`. The web forms use their existing `enquiry-forms` and `public-forms` throttles. These are disabled under the test runner, per project convention.
2. **Per number**: `MOBILE_OTP_MAX_SENDS_PER_NUMBER_PER_HOUR` across all purposes, enforced inside `RequestMobileOtp`, so Livewire is covered too.
3. **Per IP inside the action**: `MOBILE_OTP_MAX_SENDS_PER_IP_PER_HOUR`.
4. **Resend cooldown**: `MOBILE_OTP_RESEND_COOLDOWN_SECONDS` per number + purpose, measured from the last send that was not definitively rejected.
5. **Concurrency**: a 10-second per-number cache lock wraps the check-and-create, so two simultaneous requests cannot both pass the cooldown.
6. **Verification**: `MOBILE_OTP_MAX_ATTEMPTS` per challenge, counted under a row lock.

> **Production check.** Every per-IP limit uses `$request->ip()`. The bundled Docker image runs nginx → php-fpm in one container, so that is the client's IP. If a CDN or load balancer is put in front, configure trusted proxies (`$middleware->trustProxies(...)` in `bootstrap/app.php`). Otherwise all visitors share one IP and one 20-per-hour budget.

The API uses Laravel's default CORS (`api/*`, any origin). The rate limits are the real control, since a script doesn't need a browser. Restrict origins in a published `config/cors.php` if only your own site should call it.

---

## 9. Expiry and attempts

- The code is valid for `MOBILE_OTP_EXPIRY_MINUTES` from issue. Expired codes are refused without counting an attempt.
- Each wrong code increments `attempts`. The attempt that reaches `max_attempts` returns `too_many_attempts` and locks the challenge (`exhausted`); after that even the right code is refused.
- After verification, a challenge can be **redeemed once** by its protected action within `MOBILE_OTP_VERIFIED_TTL_MINUTES` (`consumed_at`).

---

## 10. Mobile number normalisation

`MobileNumber::normalize()` strips everything except digits, then removes a `91` prefix from 12-digit input or a `0` prefix from 11-digit input. It runs **before** validation (`/^[6-9]\d{9}$/`). Storage format: 10 digits. Sent to Areach as `91` + 10 digits. Displayed as `******1234`.

---

## 11. Code generation and storage

- `random_int` (CSPRNG) draws from `0 … 10^n − 1` and the result is left-padded, so codes such as `004213` occur. The old `100000–999999` range never issued them.
- Only `Hash::make($code)` (bcrypt) is stored. `otp_hash` is `$hidden` on the model and never shown in admin.
- The plaintext code exists only inside `RequestMobileOtp` and the SMS text. It is never returned, flashed, logged (except by the local-only `log` provider) or shown.

---

## 12. Challenge lifecycle

```
                ┌─ correct code ─────────▶ verified ─ protected action ─▶ (consumed_at set)
 pending ───────┼─ wrong code × max ─────▶ exhausted
 (SMS sending)  ├─ newer code requested ─▶ superseded
                ├─ SMS rejected ─────────▶ failed
                └─ expires_at passes ────▶ expired (derived, never stored)
```

`status` (challenge) and `sms_status` (gateway) are separate fields: an `accepted` SMS on a `pending` challenge is **not** verified. "Expired" is computed by `effectiveStatus()` and the `whereEffectiveStatus()` scope rather than stored, so no scheduler is needed.

---

## 13. Admin panel

**Catalog → Mobile OTP Verifications** (`/admin/mobile-otp-verifications`).

- The list shows the masked number, purpose, status (with expiry derived), SMS status, failure category, attempts, and requested/expiry/verified times. Provider reference and "used by form" columns can be toggled on.
- Search by the full number (the display stays masked) or by provider reference.
- Filters: status (including Expired), purpose, SMS status, requested-date range.
- The view page adds the provider, HTTP status, accepted-at time, challenge ID and IP address.
- **Read-only.** There is no create, edit or delete, even for `super_admin`, so nobody can generate or read a code or mark a number verified.
- **Access** is controlled by the Shield permissions `ViewAny:MobileOtpChallenge` and `View:MobileOtpChallenge`. No starter role has them; `super_admin` sees it via the gate bypass. Grant them in Access Control → Roles. Run `php artisan shield:generate --resource=MobileOtpChallengeResource --panel=admin --option=permissions` once per environment so the permissions are listed.
- Not audited. Challenges are high-volume records containing personal data, and the screen makes no changes, so there is nothing to audit.

---

## 14. Frontend integration

| Form | Tech | Where |
| --- | --- | --- |
| Quick Enquiry box | Alpine `quickEnquiryForm` + no-JS fallback | `resources/views/components/site/quick-enquiry.blade.php`, `resources/js/app.js` |
| Journey phone step | Alpine `journeyStep` | `resources/views/components/journey/field.blade.php` |
| Credit score check | Livewire `⚡credit-score-check` | `resources/views/components/⚡credit-score-check.blade.php` |
| Contact page phone | Alpine `contactPhoneOtp`, calling the public API | `resources/views/contact.blade.php`, `resources/js/app.js` |

What the forms share:

- **Resend countdown.** `otpResendTimer()` is spread into Alpine components, and `x-data="otpResendCountdown(seconds)"` is used in Livewire views. It is seeded from `resend_after_seconds`/`retry_after_seconds` and counts down to a deadline, so it stays correct in background tabs.
- **Code length.** Comes from `MobileOtpChallenge::codeLength()` and is passed to Blade and JS.
- **Number change.** Changing the number after a code was sent resets the OTP state (journey watcher; Quick Enquiry "Change number").
- **Busy states.** Buttons are disabled while a request is in flight, to prevent duplicate submissions.
- **The server is the gate.** Every check is repeated on the server. Livewire `step`, `otpChallengeId` and `verifiedMobileNumber` are `#[Locked]`, and the check runs against the locked verified number.

To protect a new form: issue with `RequestMobileOtp` under a new `MobileOtpPurpose`. On submit, `findForVerification(id, number, purpose)`, then `VerifyMobileOtp`, then `consumeFor(number, purpose)` inside the same DB transaction that writes the protected record.

---

## 15. Using the API from another client

1. `send`, then show "code sent to ******3210", and count down `resend_after_seconds`.
2. `verify` with the code the user typed.
3. For the Quick Enquiry lead: `POST /quick-enquiry` (web route, CSRF-protected) with `phone` and `otp_challenge_id` and **no** `otp_code`. The server redeems the verified challenge once and records the lead.

---

## 16. Adding another SMS provider

1. Create `app/Modules/MobileOtp/Providers/AcmeSmsProvider.php` implementing `SmsProvider`. Return `SmsSendResult::accepted/unconfirmed/failed`, never throw, never retry, never log the message.
2. Put credentials in `config/services.php` under `acme`, read from env.
3. Register `'acme' => AcmeSmsProvider::class` in `config/mobile_otp.php` → `providers`.
4. Set `MOBILE_OTP_SMS_PROVIDER=acme`. No controller, form or action changes are needed.
5. Add a provider test like `tests/Feature/MobileOtp/AreachSmsProviderTest.php` using `Http::fake()`.

---

## 17. Troubleshooting

Start at Admin → Mobile OTP Verifications, filtered to **SMS status = Failed** or **Unconfirmed**. The log has a masked warning per non-accepted send: `Mobile OTP: SMS not confirmed as accepted.` with the challenge, purpose, masked number, provider, reason and HTTP status.

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `not_configured` | Key or sender missing, or `log` provider outside local | Set the env vars; `php artisan config:clear` (or re-cache). |
| `http_error` 401/403 | Wrong or revoked key | Regenerate the key in Areach and update the server env. |
| `provider_rejected` | Sender or DLT template mismatch, or insufficient balance | Check the template text against the DLT approval and the Areach dashboard. |
| `timeout` / `unconfirmed` | Provider slow or response unrecognised | Check the Areach dashboard for the message before resending. Tighten the parser (§2). |
| `connection_failed` | DNS or egress blocked | Check outbound HTTPS to `api.areach.in` from the server. |
| Accepted but not received | Operator or DLT filtering, DND, wrong number | Accepted is not delivered. Check Areach delivery reports. |
| Everyone gets "Too many OTP requests" | Proxy hides client IPs | Configure trusted proxies (§8). |

---

## 18. Tests

```bash
php artisan test --compact tests/Feature/MobileOtp
php artisan test --compact tests/Feature/QuickEnquiryTest.php tests/Feature/Journey/PhoneVerificationTest.php tests/Feature/CreditScore tests/Feature/Filament/MobileOtpChallengeAdminTest.php
php artisan test --compact     # full suite
```

The suite never sends SMS. `phpunit.xml` forces `MOBILE_OTP_SMS_PROVIDER=log` and an empty `AREACH_API_KEY`. Areach is exercised only behind `Http::fake()` with `Http::preventStrayRequests()` (`fakeAreachSms()` / `lastTextedOtp()` in `tests/Pest.php`).

---

## 19. Live smoke test (one real SMS)

This is separate from the automated tests and is the only sanctioned way to send a real code outside the site.

```bash
# 1. On the target server, with a NEWLY REGENERATED key in the environment:
#    MOBILE_OTP_SMS_PROVIDER=areach, AREACH_API_KEY=..., AREACH_SENDER=FYNNED
#    MOBILE_OTP_LIVE_TEST_ENABLED=true
php artisan config:clear

# 2. Send ONE SMS to a number you are authorised to text:
php artisan mobile-otp:smoke-test 98XXXXXXXX --purpose=loan_enquiry

# 3. Enter the code from the phone when prompted (input is hidden), or leave it
#    blank and verify through the real endpoint with the printed curl command.

# 4. Turn the flag off again:
#    MOBILE_OTP_LIVE_TEST_ENABLED=false && php artisan config:clear
```

The command refuses to send unless all of these hold: the flag is true, the provider is `areach`, a key is set, and you confirm (or pass `--confirm`). It sends once and never retries; if the result is `unconfirmed` it tells you not to run it again until you know whether the SMS arrived. It prints the challenge ID, SMS status, HTTP status, provider reference, failure category and response shape, but never the code or the key. Success means: SMS accepted, code received, status stored as **Verified** (visible in the admin panel).

---

## 20. Production secrets

- Use a **newly regenerated** Areach key. Any key that has been pasted into a chat, ticket or document must be revoked.
- Put it only in the server's environment or secret manager (e.g. Laravel Cloud environment variables, Docker secrets or env injection), never in `.env.example`, Git, Blade, JS, logs or exception messages.
- After changing env on a server that caches config, run `php artisan config:cache`.

---

## 21. Deployment and rollback

```bash
php artisan down                               # optional; the migration is additive and fast
git pull && composer install --no-dev --optimize-autoloader
php artisan migrate --force                    # additive only, never migrate:fresh
php artisan shield:generate --resource=MobileOtpChallengeResource --panel=admin --option=permissions
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

**Order matters.** Set `AREACH_API_KEY`, `AREACH_SENDER` and `MOBILE_OTP_SMS_PROVIDER=areach` **before** deploying. With the new code and no key, every OTP send fails safely (`not_configured`) and visitors see "could not send a verification code"; they do not see a demo code.

**Rollback.** Deploy the previous release, then `php artisan migrate:rollback --step=1`. That drops only the added columns, so send metadata, purpose and status for challenges created since are lost, while the original columns and rows are kept. The old code returned demo codes on screen, so plan a forward fix rather than a long rollback.

---

## 22. Privacy, logging and security

- Codes are bcrypt-hashed, compared with `Hash::check`, and never returned or logged (except by the local-only `log` provider).
- Numbers are masked in API responses, admin lists and logs. The full number is stored for verification and searchable by authorised staff.
- The provider's raw responses are never stored or shown, only normalised categories, an HTTP status and an id-like reference.
- Challenge IDs are random UUIDs. Possessing one is useless without the number, purpose and code.
- An accepted SMS, provider delivery, or a browser flag (`mobile_verified`, `phone_verified`) never establishes verification. Only `VerifyMobileOtp` returning `Verified` does.
- Admin: read-only and permission-gated, and cannot create, edit or delete a challenge.
