# FynnOn customer portal

How customers of **FynnOn by Markedge Technologies** sign in, see their saved credit scores and reports, keep their details up to date and apply for loans. Covers what was reused, the routes, the identity rules, the refresh policy, security controls, configuration, tests and deployment.

> **The one rule.** Signing in, opening the dashboard or viewing a saved report **never** contacts a credit bureau. A new bureau enquiry only ever happens through the existing credit-score check, after an OTP to the customer's own mobile **and** a freshly ticked consent box, and only when the bureau's existing reuse period allows it.

---

## 1. What already existed, and was reused

| Need | Existing piece reused | What changed |
| --- | --- | --- |
| Customer identity | `customers` table / `App\Modules\Customers\Models\Customer` (staff-managed lead record, keyed by email) | Same row is now also the portal account (new columns, `customer` session guard). No second identity table. |
| Staff login | `users` + `web` guard + Filament + Shield | Untouched. Customers use a separate `customer` guard on the same session system. |
| Mobile verification | `App\Modules\MobileOtp` (`RequestMobileOtp`, `VerifyMobileOtp`, `consumeFor`) | New purpose `customer_login`; refused by the public OTP API. |
| Credit-score check | `credit-score-check` Livewire component, `SubmitCreditScoreCheck` (reuse window, per-person lock, 60-minute uncertain hold), live Surepass providers | Resolves the portal account from the OTP-verified mobile, records `credit_score_checks.customer_id`, signs the visitor in, offers "Open my profile". |
| Bureau reports | `cibil_credit_requests`, `equifax_credit_requests`, `experian_credit_requests` (full encrypted report via `RecordWebsite*Check`), `*ReportDocument` parsers | Read-only. Portal resolves a check's report through the id the provider stored (`raw_response.{bureau}_request`). |
| Loan applications | Journey → eligibility → lender selection → `applications` → `application_documents` → submit | Portal starts/resumes the same journey; prefill for the first step. |
| Documents | `application_documents` on the private `local` disk | Portal lists and downloads the customer's own; uploads stay on the application page. |
| Audit | `audit_logs` (`Auditable`, `recordEvent()`) | Customer is now `Auditable` (PII excluded); portal events recorded. |
| Help & support | Contact form, contact settings, grievance levels | Linked from the portal. **No ticketing module exists**, so none was built. |
| Lead record | `journey_sessions` (the "Applications" funnel in admin) | A portal application is a normal journey session; no `contact_enquiries` lead is created for it. |

---

## 2. Routes

| URL | Name | Notes |
| --- | --- | --- |
| `GET /existing-customer` | `customer.login` | Existing Customer page (`guest:customer`). OTP sign-in Livewire component `customer-login`. |
| `GET /my-profile` | `portal.dashboard` | Dashboard |
| `GET\|PUT /my-profile/personal-information` | `portal.profile`, `portal.profile.update` | My Profile |
| `GET /my-profile/credit-score` | `portal.credit-score` | Per-bureau latest result + refresh availability |
| `GET /my-profile/credit-score/{bureau}/refresh` | `portal.credit-score.refresh` | Hosts the credit-score-check component (`context="portal"`) only when a refresh is available |
| `GET /my-profile/credit-reports` | `portal.reports.index` | Report history (paginated) |
| `GET /my-profile/credit-reports/{uuid}` | `portal.reports.show` | Human-readable report |
| `GET /my-profile/credit-reports/{uuid}/pdf` | `portal.reports.pdf` | Stored PDF only |
| `GET /my-profile/apply-for-loan` | `portal.apply` | Products with a live journey |
| `POST /my-profile/apply-for-loan/{loanProduct:slug}` | `portal.apply.start` | Resume or start |
| `GET /my-profile/loan-applications` | `portal.applications` | Drafts, eligibility-ready, applications |
| `GET /my-profile/documents`, `/documents/{uuid}` | `portal.documents`, `portal.documents.show` | List / download |
| `GET /my-profile/help` | `portal.support` | Help & Support |
| `GET /my-profile/settings` | `portal.settings` | Account Settings |
| `POST /my-profile/logout` | `portal.logout` | Sign out |

The public credit-score pages keep their URLs (`/credit-score/{bureau}`) and SEO settings. The header's language strip shows **Existing Customer? Sign in** or **My Profile**. `/my-profile` and `/existing-customer` are in robots.txt `Disallow`, and every portal page sends `noindex, nofollow` and `Cache-Control: no-store, private`.

Every `/my-profile` route runs `auth:customer` → `customer.portal` (`EnsureCustomerPortalAccess`) → `throttle:customer-portal` (60/min per customer).

---

## 3. Authentication

- **Guard:** `customer` (session driver, `customers` Eloquent provider) in `config/auth.php`. Staff keep `web`.
- **Method:** mobile number + one-time code (SMS through the existing Areach integration). There is **no password** and no "remember me" token, so there is nothing to reset and no credential in the database. "Account recovery" is signing in again with an OTP.
- **No enumeration:** every well-formed number gets a code and the same answer. Only after the code is verified is the visitor told that no profile exists for that number.
- **Throttling:** per-number and per-IP send limits and the resend cooldown live in `RequestMobileOtp`; each code allows `mobile_otp.max_attempts` tries; the login form adds a per-IP wrong-code limit (`CUSTOMER_PORTAL_LOGIN_MAX_ATTEMPTS`, default 10 per `CUSTOMER_PORTAL_LOGIN_DECAY_MINUTES`, default 15) because Livewire actions bypass route throttles. Codes expire after `mobile_otp.expiry_minutes` and are redeemed once (`consumeFor`).
- **Session:** `LogInCustomer` regenerates the session id; logout invalidates the session and the CSRF token. A staff-disabled account is signed out at its next request. After a signed-out visitor is sent to the login page, they return to the `/my-profile/...` page they asked for (any other stored destination is ignored).
- **Default guard reset:** `auth:customer` makes `customer` the request's default guard; `EnsureCustomerPortalAccess` puts `web` back so `Auth::id()` can never return a customer id. `Auditable` also reads the `web` guard by name, because `audit_logs.user_id` references `users`.
- **Production cookies:** set `SESSION_SECURE_COOKIE=true` (HTTPS only). `http_only` is already on and `same_site` is `lax`.

---

## 4. Account activation and identity linking

`App\Modules\Customers\Actions\ResolveCustomerAccount` is the only place a verified mobile becomes an account:

1. the account whose `verified_mobile` is that number; otherwise
2. **exactly one** existing, unclaimed customer whose contact `phone` is that number is activated (journey phones are OTP-verified; staff enter the rest); otherwise
3. a new account — **only** from the credit-score check (`forVerifiedMobile`). The login page (`forLogin`) never creates one.

`verified_mobile` is unique, so two simultaneous first checks cannot create two accounts. A typed PAN, name or email never selects an account. Several unclaimed records sharing a phone are **not** activated (no safe choice).

**Public credit-score check:** after OTP + consent, the check is recorded against the resolved account and the visitor is signed in. On the public page the mobile is never prefilled and nothing of a signed-in customer is shown until an OTP proves the number is theirs, so a second person on a shared browser checks their own number into their own account (the browser switches to that account). On the portal refresh page the mobile is locked to the account's verified number.

---

## 5. Profile

| Kind | Fields | Rule |
| --- | --- | --- |
| Verified | `verified_mobile` (OTP), identity (`identity_verified_at`, staff only, after KYC) | Mobile changes only through support. Once identity is verified, name/DOB/gender are read-only to the customer. |
| Customer-provided | name, DOB, gender, email, address, PAN | Editable (PAN: add once, change only through support). |
| Bureau-reported | personal data inside a report | Shown only in the report viewer, labelled as reported by the bureau. **Never copied into the profile.** |

After a check the bureau matched, `Customer::fillBlankProfileDetails()` fills only **blank** fields with what the customer typed into that check (still customer-provided). PAN is stored encrypted (`pan_encrypted`) with `pan_masked` and an HMAC `pan_hash`; the UI only ever shows the masked PAN. Profile changes are recorded as `profile_updated` with field **names** only.

---

## 6. Credit reports, reuse and the monthly refresh policy

- The portal lists only **genuine** checks (`provider = surepass`), never demo scores. Each bureau is shown separately; scores are never merged or compared across bureaus. A check answered from a stored score (`raw_response.reused_from`) is a repeat view, not a new report.
- **Regeneration limit (user decision 17 Oct 2026, replaces the 30-day policy approved on 16 Oct):** every check is free; a person gets **one new bureau report every 3 months, at most 4 in any 12 months, per customer and per PAN, across all bureaus** (`CreditReportRegenerationLimit`, §7). The bureau's own reuse period (30 days by default) still applies on top.
- **States** (`CustomerCreditReports::refreshEligibility()`): `available`, `too_soon` (next date shown — the later of the reuse period and the regeneration limit), `in_progress` (a pending check younger than the 60-minute uncertain hold), `unavailable` (the bureau's page is not live).
- **Refresh flow:** Refresh → `/my-profile/credit-score/{bureau}/refresh` → OTP to the registered mobile → details prefilled from the last check (editable) → consent box → the same `SubmitCreditScoreCheck` as the public page (reuse window, per-person lock, uncertain hold, `RecordWebsite*Check` for full reports). A refresh adds a row; earlier reports are never overwritten, and a failed or uncertain refresh leaves the last successful report in place. No automatic retries.
- **Report viewer:** `CreditReportView` reads the bureau request through the existing `CibilReportDocument` / `EquifaxReportDocument` / `ExperianReportDocument` and shows only labelled sections (personal information, masked identity documents, addresses, account summary, accounts with payment history, enquiry summary, enquiries). Raw JSON, provider envelope fields and unlabelled extras are not shown. Each view is logged as `viewed_by_customer` on the bureau request (no staff `user_id`).
- **PDF:** served only when the bureau request already holds an encrypted copy on the private disk (e.g. CIBIL's inline PDF). A provider's temporary link is never fetched, followed or shown from the portal; buying a PDF stays a staff action.
- **CRIF:** the public CRIF check keeps only the score (no full report is stored), so its reports show "Only the score from this check was saved".

---

## 7. Free checks and the regeneration limit

User decision (17 Oct 2026), after reviewing the Surepass service agreement: **every credit-score check is free**. The agreement grants use "exclusively for the Purpose and not for subsequent resale" (§2.1) and forbids reselling or selling access to the Products (§2.1.2, §8.2(c)), so the paid-refresh, wallet and plan feature built on 17 Oct was removed (never deployed). The agreement sets **no** regeneration frequency; the limit below is the business's own rule, which also caps per-transaction bureau costs (Schedule A: ₹70 CIBIL, ₹20 CRIF, ₹15 Experian, ₹15 Equifax per successful 200/422 response, plus taxes).

**Rule** (`App\Modules\CreditScore\Services\CreditReportRegenerationLimit`, config `customer_portal.regeneration`): one **new** bureau report every **3 months** and at most **4 in any 12 months**, counted **per customer and per PAN, across all bureaus**. A new report is a genuine check the bureau answered (score or "no score yet"), not one answered from a stored score; failed, uncertain and demo checks do not count.

**What a visitor sees inside the limit** (the credit-score component, public page and portal refresh alike):

| Situation | Result |
| --- | --- |
| Same bureau, same PAN and mobile as a recent report | The saved score again (no new pull), with the date the next new report is available |
| Another bureau, or the same PAN on this account through another number | Refused **before** the bureau is asked or the OTP is spent; shows the last report's bureau and date, the next date, and "View my saved report" |
| The same PAN on **another** account | Refused with a generic message — no dates or bureau, so a typed PAN reveals nothing |

The decision and the check run under a per-person cache lock, so two tabs cannot use one allowance on two bureaus. The portal's My Credit Score / refresh page, the details step after OTP, and the admin Customer page ("Next new credit report") all show the next date.

---

## 8. Loan applications

- **Products:** published loan products with an active journey definition only.
- **Start/resume:** `StartPortalLoanApplication` resumes the customer's unfinished session for that product, else starts one with `StartJourneySession` and links it (`customer_id`, `phone_number` = verified mobile, `phone_verified_at`). A cache lock per customer + product stops double clicks creating two sessions.
- **Prefill:** `LoanApplicationPrefillService` offers full name, verified mobile, email, DOB, city and PAN — shown in the form with a notice saying which are verified. Nothing is saved until the customer submits the step; journey validation is unchanged. Loan details (employment, income, amount, tenure, property, business) are always asked and never derived from a credit score.
- **Ownership:** a session/application is the customer's when its OTP-verified phone equals the account's verified mobile (`JourneySession::scopeVerifiedFor`). Being linked to the customer record by a typed email is not enough.
- **Email linking hardened:** `SubmitJourneyStepResponses::linkCustomer()` no longer re-links a portal session to another record, and never rewrites a portal account's name or phone from journey input.
- The existing workflow continues unchanged: eligibility, lender selection, documents, submission, statuses (`ApplicationStatus`) and admin processing.

---

## 9. Database changes

| Migration | Change |
| --- | --- |
| `2026_10_16_133734_add_portal_account_columns_to_customers_table` | `email` nullable; `verified_mobile` (unique), `mobile_verified_at`, `portal_activated_at`, `portal_disabled_at`, `last_login_at`, `date_of_birth`, `gender`, `pan_encrypted`, `pan_masked`, `pan_hash` (index), `identity_verified_at`, `address_line1`, `address_line2`, `city`, `state`, `pincode` |
| `2026_10_16_133735_add_customer_id_to_credit_score_checks_table` | `customer_id` FK (nullOnDelete) + index `(customer_id, bureau, completed_at)` |


Both are additive and reversible. Rolling back the first restores `email NOT NULL` only if no customer is missing an email. Existing checks stay unlinked (they were never tied to a verified account).

---

## 10. Admin (Filament)

Customers resource:

- **Portal** column and **Portal account** filter.
- Read-only **Customer portal account** section showing status, masked verified mobile, activation, last sign-in, identity verification, profile completion, masked PAN, DOB, gender and address.
- Header **Portal account** menu: *Mark identity as verified*, *Disable portal sign-in*, *Re-enable portal sign-in*. Each needs `Update:Customer` and is recorded in History with the staff user.
- Tabs: **Website score checks** (the account's checks; demo ones labelled as not shown to the customer) and **History** (audit, `View:AuditLog`).
- `email` is no longer required when editing (portal accounts may have none).

No passwords, OTPs or tokens exist to expose; PAN and mobile are masked.

---

## 11. Queue and scheduler

Nothing new. Portal reads are synchronous and make no provider calls. Refreshes use the existing synchronous public check. The existing `*:flag-stale` / `*:enforce-retention` schedules (routes/console.php) continue to apply; a purged report shows as "only the score was saved".

---

## 12. Configuration

| Key | Env | Default |
| --- | --- | --- |
| `customer_portal.brand` | `CUSTOMER_PORTAL_BRAND` | `FynnOn by Markedge Technologies` |
| `customer_portal.login_max_attempts` | `CUSTOMER_PORTAL_LOGIN_MAX_ATTEMPTS` | `10` |
| `customer_portal.login_decay_minutes` | `CUSTOMER_PORTAL_LOGIN_DECAY_MINUTES` | `15` |
| `customer_portal.regeneration.every_months` | `CREDIT_REPORT_REGENERATE_EVERY_MONTHS` | `3` |
| `customer_portal.regeneration.max_per_year` | `CREDIT_REPORT_MAX_PER_YEAR` | `4` |

Also relevant: `SESSION_SECURE_COOKIE=true` in production, `SESSION_LIFETIME` (minutes), and the existing `MOBILE_OTP_*` and bureau settings.

---

## 13. Tests

```
php artisan test --compact tests/Feature/CustomerPortal tests/Feature/Filament/CustomerPortalAdminTest.php
```

All bureau calls are faked (`fakeSurepassExperian()`, `fakeAreachSms()`, `Http::preventStrayRequests()`); synthetic PANs and mobiles only.

---

## 14. Deployment (after approval)

1. `php artisan migrate --force`
2. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
3. `npm run build` (new Blade views use existing Tailwind tokens; the build picks up their classes)
4. Set `SESSION_SECURE_COOKIE=true`; confirm the Areach SMS settings and each bureau's live switch.
5. The regeneration limit is 3 months / 4 a year (`CREDIT_REPORT_REGENERATE_EVERY_MONTHS`, `CREDIT_REPORT_MAX_PER_YEAR`); each bureau's own reuse days (Settings page) still apply on top.

**Rollback:** `php artisan migrate:rollback --step=2` (see §8 for the email column), then deploy the previous release. Portal routes disappear; existing credit-score, journey and admin flows are unaffected by the rollback.

---

## 15. Known limitations

- No password sign-in or email-based recovery (by design: OTP only).
- Changing the registered mobile or PAN is a support task; there is no self-service flow.
- No ticketing system: Help & Support links to the contact form, phone, email and grievance levels.
- CRIF's public check stores no full report, so CRIF reports show the score only.
- Customer PDFs are available only where a bureau request already stored one; the current Surepass plans (16 Oct 2026) do not include the PDF endpoints for Experian/Equifax.
- Applications started before this change are visible in the portal only if their journey phone was OTP-verified with the same number.
- Several unclaimed customer records sharing one phone are not merged or activated automatically; staff can merge them.
- Customers are never charged: the Surepass agreement forbids resale (§2.1, §2.1.2, §8.2(c)).
- To confirm with Surepass: §2.1.1 describes the API being made available "to its employees and other internal users" and §2.1.1/§2.1.2 forbid sharing access with third parties, including Clients. The public self-service score page lets visitors trigger bureau calls through the website, so its fit with the agreement's Purpose should be confirmed in writing.
- If two different accounts check the same PAN for the first time at the same instant, both can get a new report (the lock is per person).
