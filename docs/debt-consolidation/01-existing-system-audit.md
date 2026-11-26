# 01 — Existing system audit (Phase 0)

**Date:** 25 Nov 2026 · **Branch:** `feature/nov_24_redis_check` · **Method:** source code, migrations, the local MySQL database (read-only queries) and the existing test suite. A feature only counts as "existing" below when its persistence, backend processing and tests were found, not because a menu item exists.

## 1. Platform

| Item | Found |
| --- | --- |
| PHP | 8.5.4 (composer constraint `^8.3`); no `bcmath` extension |
| Laravel | 13.29 |
| Filament | 5.7.6, with filament-shield 4.3 and spatie/laravel-permission 8.3 |
| Livewire | 4.4 (single-file `⚡` components in `resources/views/components`) |
| PDF | barryvdh/laravel-dompdf 3.1 (used once: portal credit report download) |
| Front end | Tailwind CSS v4 (CSS-variable tokens), Alpine.js 3, Vite 8 |
| Tests | Pest 5, SQLite in-memory, ~2,350 tests in 279 files |
| Database | MySQL in dev/production, 84 tables, 157 migrations |
| Queue / cache / session | `database` drivers in `.env` (Redis documented in `docs/redis-cache.md`) |
| Languages | Public site is English + Hindi (`__()` + `lang/hi.json`); admin is English only |

## 2. Reuse map

| Need for Debt Consolidation | Existing piece | Verdict |
| --- | --- | --- |
| Customer identity and login | `App\Modules\Customers\Models\Customer`, `customer` session guard, `ResolveCustomerAccount`, `LogInCustomer`, `/my-profile` portal (`EnsureCustomerPortalAccess`, `throttle:customer-portal`) | **Reuse unchanged.** The DC journey signs the visitor in after OTP exactly like the credit-score check. |
| Mobile OTP | `App\Modules\MobileOtp` (`RequestMobileOtp`, `VerifyMobileOtp`, `MobileOtpChallenge::findForVerification/consumeFor`, Areach SMS provider) | **Extend:** one new purpose `debt_consolidation`. No migration. |
| Simple enquiry / lead pipeline | `contact_enquiries` + `RecordEnquiry` (dedupe, reopen), Lead Inbox, Lead Analytics, FynnOn LMS push (all listen to `ContactEnquiry`) | **Extend:** new `EnquiryType::DebtConsolidation`; DC enquiries dedupe against earlier DC enquiries for the number. Every DC lead therefore reaches the Lead Inbox and LMS with no new lead source. |
| Loan application journey | `Journey` module (journey_definitions/steps/fields/sessions) | **Not reusable for DC:** no repeating groups (cannot collect a variable list of loans), every journey requires a `loan_product_id`. A dedicated multi-step Livewire flow is built instead. |
| Lender application | `Applications` module (`applications`: one row per journey session × lender product; documents per application) | **Not reusable as the DC case:** one lender product per row, journey session required, public routes have no ownership check. DC lender applications are tracked in their own table linked to the existing `lender_products`. |
| Documents | `document_types` catalogue, private `local` disk, portal secure download pattern, bureau `.enc` encrypted-at-rest pattern | **Reuse the catalogue and storage pattern.** DC documents get their own table (versioned, encrypted at rest, linked to liabilities/closures) because `application_documents` is keyed to `applications` and overwrites files in place (no history). |
| Lenders and offers | `lenders`, `lender_products` (lender × loan product, amounts, tenure, fees), `lender_product_rates` + `RateBook` | **Reuse; extend** with a DC policy table keyed by `lender_product_id`. No duplicate lender directory. |
| Lender eligibility rules | `Eligibility` module (versioned rule sets per lender product, `EligibilityEngine::evaluateAttributes()`) | **Reuse** as the lender's general applicant rules inside DC matching (with the DC-correct remaining EMI). DC-specific criteria (accepted liability types, existing-lender takeovers, collateral, DSCR) cannot be expressed there (no list operators, FOIR adds the new EMI to all existing EMIs) and live in the DC policy. |
| EMI maths | `App\Support\Calculators\EmiCalculator`, `PrepaymentCalculator` | Single-loan only. The DC engine has its own multi-liability engine; its EMI agrees with `EmiCalculator` to the paisa (pinned by a test). |
| CMS pages | `pages` (title, excerpt, body, status, schedule, preview, Hindi, audit, FAQs morph, `seo_metas`) via Website Pages → General Pages | **Reuse.** Each DC URL is a `pages` row. Section copy follows the About page precedent (Page row for SEO/publish/preview + a settings screen for section wording). |
| SEO | `Seoable`/`SeoFormSection`, `PageSeo` overrides, `SchemaGraph`, `Sitemap`, `CrawlerPolicy`, FAQ JSON-LD | **Reuse unchanged;** DC routes are added to `Sitemap` through the existing `pageRoute()` mechanism. |
| FAQs | `faqs` morph to `Page` + `FaqsRelationManager` | **Reuse:** DC FAQs are the FAQs tab of each DC page. |
| Admin shell | Filament clusters (`AdminModules`, `BelongsToAdminModule`), `AdminGuide` help, Shield permissions, `audit_logs` (`Auditable`) | **Reuse;** one new cluster "Debt Consolidation" under Operations. |
| Staff/roles | `users` + Shield roles (`RoleSeeder`, `*Roles::grant()` pattern) | **Reuse;** new `Debt Advisor` role granted by migration. |
| Notifications | Staff alert mails; newsletter mailables; database `notifications` table exists (unused for customers) | **Extend:** customer notifications (mail + database) for DC events. No general SMS sender exists (OTP only; DLT templates needed) — SMS updates are an external dependency. |
| API | `api-client` guard (`AuthenticateApiClient`, service-account user, policies), `Api\V1` controllers/resources | **Reuse** for the FynnAI-ready read API. |

## 3. Existing debt-related content (must be preserved)

| Item | Where | Decision |
| --- | --- | --- |
| Loan landing pages "Personal Loan for Debt Consolidation", "Loan Against Property for Debt Consolidation", "Gold Loan for Debt Consolidation", "Term Loan for Debt Refinancing", Home/Car/Two-wheeler Balance Transfer | `loan_landing_pages` (`LoanLandingPageSeeder::seedByNeed()`); production slugs differ (`/loans/personal-loans/...`) | **Kept unchanged.** The new hub links to them by query (purpose pages of the financing routes), never by hard-coded slug. They target "<product> for debt consolidation" searches; the hub targets the service itself, so they complement rather than duplicate. |
| 301 `/how-to-combine-multiple-loans-and-reduce-your-emi-burden-in-india-in-2026` → personal-loan-for-debt-consolidation | migration `2026_10_31_084215` | Kept. |
| `loan_purpose = debt-consolidation` journey option | `JourneySeeder` | Kept (nothing reads it). |
| Personal loan copy and one article mention consolidation | seeders | Kept. |

No `/debt-consolidation` route, page, redirect, product, calculator, FAQ or 404 log entry exists, so the seven new URLs do not collide with anything.

## 4. Constraints discovered

1. **Trailing slashes.** The site's canonical URLs have none; `/x/` is answered with a single 301 to `/x` (`HandleRedirects`, pinned by `RedirectsTest`). The mandate lists URLs with trailing slashes. They are served at `/debt-consolidation`, `/debt-consolidation/how-it-works`, … so every page keeps one canonical form; visiting the slashed form 301s to it. Changing the sitewide convention would alter every existing canonical URL, which the mandate forbids.
2. **Nested CMS slugs.** General pages only answer single-segment slugs. The four nested URLs are explicit routes bound to `pages` rows (like `/grievance`), and their slugs are locked in the editor.
3. **Bilingual site.** Every visitor string goes through `__()` with Hindi in `lang/hi.json`. PDFs stay English (dompdf cannot shape Devanagari).
4. **Admin conventions.** A new admin screen needs an `AdminModules::placements()` row and an `AdminGuide::screens()` entry (enforced by `AdminNavigationTest`/`AdminHelpTest`); enums need `HasLabel`; Modules models need `newFactory()`.
5. **Migrations.** Explicit short index names on long table names; never `->after()`; production MySQL differs from local.
6. **Rule: config-driven, no per-product branching** (`.ai/rules/app.md`). DC routes, liability types and lender rules are data/config, not `if ($product === …)` code.
7. **No bcmath.** Money maths uses IEEE doubles rounded to the paisa at every period (deterministic on a given build); see the financial engine specification.

## 5. Baseline test run

Recorded in `08-testing-report.md` (full suite run before any change).
