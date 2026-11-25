# FynnEdge CMS restructure — implementation report

**Date:** 24 Nov 2026 · **Branch:** `feature/nov_24_redis_check` (uncommitted, not deployed) · **Environment:** local development

## A. Executive summary

The admin panel has been reorganised from 9 scattered sidebar groups (76 screens) into **9 main modules** in the left sidebar, with each module's screens as **tabs across the top of the page**. Every existing admin URL and route name is unchanged.

New capabilities, all built on the **existing database schema** (no table, column, index or foreign key was added or changed):

- **Leads & Enquiries module**: one **Lead Inbox** for every website lead (enquiries, credit score checks, online and loan applications, OTP verifications), with source page, campaign, verification, LMS status, a per-person journey view and CSV export.
- **FynnOn LMS push**: every new lead is queued for FynnOn LMS the moment it is saved, with retries, idempotency, a delivery log, failure alerts and manual resend. It is **switched off until the LMS address and key are provided** (see L).
- **Lead Analytics**: page-wise, form-wise, product-wise and campaign-wise leads, OTP completion, LMS delivery, trends, comparison with the previous period, factual insights and CSV export. Conversion rates use real, cookie-free page-view counts.
- **Create a Page guide**: ends the "Pages vs Loan Landing Pages" confusion. New general pages now really get a public address (`fynnedge.com/{address}`); before this change they had none.
- **Authors**: author profiles with photo; author byline, "About the author" box, author page and Person structured data on articles.
- **Built-in help**: every one of the 81 screens explains itself (summary under the title, an "About this screen" panel, live "Before you start" prerequisites), plus a **Help & Guides** page with step-by-step workflow diagrams that show live progress.
- **Redesigned dashboard and visual theme**.

**Status: COMPLETED WITH DOCUMENTED LIMITATIONS** (section M).

## B. Before and after

| | Before | After |
|---|---|---|
| Sidebar | 9 groups, ~80 items, CIBIL/Experian/Equifax/CRIF as 4 separate groups, content split across Content / Catalog / Website Settings | Dashboard, Help & Guides, then 9 modules under 4 headings (Operations, Website, Growth, System) |
| Sub-modules | Mixed into the sidebar | Tabs at the top of every screen in the module (dropdown tabs for sub-groups, e.g. CIBIL ▾, Experian ▾); a select menu on phones |
| Lead data | 5 separate screens in 2 groups | One Lead Inbox + analytics + LMS sync, all in "Leads & Enquiries" |
| New general pages | Could be created but had **no public URL** | Served at `fynnedge.com/{address}`, in the sitemap, with preview |
| Help | None | Per-screen help, prerequisites and workflows |

## C. Module mapping

All routes are unchanged. "Moved + renamed" only changes the label shown in the menu.

| Old screen | Old group | New module | New tab | Route (unchanged) | Action | Purpose |
|---|---|---|---|---|---|---|
| API Clients | Access Control | Administration | API Clients | /admin/api-clients | Moved | Other systems (such as the LMS) allowed to call the FynnEdge API with their own key. |
| Activity | Access Control | Administration | Activity | /admin/admin-activity | Moved | Who changed what in the admin, and when. |
| Roles | Filament Shield | Administration | Roles | /admin/shield/roles | Moved | Roles and their permissions (what each role may see and do). |
| Users | Access Control | Administration | Users | /admin/users | Moved | Admin accounts and their roles. |
| Articles | Content | Blog & Content | Articles | /admin/articles | Moved | Blog articles and guides, with category, image, author, SEO and scheduling. |
| Authors | New screen | Blog & Content | Authors | /admin/authors | New | The people who write FynnEdge articles: name, photo, designation and bio. |
| General FAQs | Content | Blog & Content | General FAQs | /admin/faqs | Moved | General FAQs shown on the /faqs page. |
| Media Library | Content | Blog & Content | Media Library | /admin/media-governance | Moved | Every uploaded image and video: where it is used, and which files are unused or missing. |
| Page FAQs | Content | Blog & Content | Page FAQs | /admin/page-faqs | Moved | FAQs pinned to specific pages (calculators, credit score pages, contact…). |
| Achievements | Content | Blog & Content | Social proof Achievements | /admin/achievements | Moved | Headline numbers shown in the homepage stats strip. |
| Life at FynnEdge Photos | Content | Blog & Content | Social proof Life at FynnEdge Photos | /admin/company-photos | Moved | Photos for the "Life at FynnEdge" gallery. |
| Social media videos | Content | Blog & Content | Social proof Social media videos | /admin/social-videos | Moved | Videos from FynnEdge's Instagram, Facebook, LinkedIn and YouTube posts. |
| Testimonials | Catalog | Blog & Content | Social proof Testimonials | /admin/testimonials | Moved | Written customer testimonials. |
| Video testimonials | Catalog | Blog & Content | Social proof Video testimonials | /admin/video-testimonials | Moved | Customer video testimonials, pinned to chosen pages. |
| CIBIL Audit History | CIBIL | Credit Bureau | CIBIL CIBIL Audit History | /admin/cibil-audit-history | Moved | Every recorded CIBIL event in one feed. |
| CIBIL Dashboard | CIBIL | Credit Bureau | CIBIL CIBIL Dashboard | /admin/cibil-dashboard | Moved | CIBIL operations: paid requests, savings from reuse, failures and items needing review. |
| CIBIL Requests | CIBIL | Credit Bureau | CIBIL CIBIL Requests | /admin/cibil-requests | Moved | Every paid CIBIL request (score, report, PDF) with its report for authorised staff. |
| CIBIL Settings | CIBIL | Credit Bureau | CIBIL CIBIL Settings | /admin/cibil-settings | Moved | CIBIL business rules and the public Get CIBIL Score page switches. |
| CRIF Dashboard | Credit Bureau | Credit Bureau | CRIF CRIF Dashboard | /admin/crif-dashboard | Moved | CRIF operations: paid requests, savings from reuse, failures and items needing review. |
| CRIF Reports | Credit Bureau | Credit Bureau | CRIF CRIF Reports | /admin/crif-reports | Moved | Every paid CRIF report attempt with its report for authorised staff. |
| CRIF Settings | Credit Bureau | Credit Bureau | CRIF CRIF Settings | /admin/crif-settings | Moved | CRIF report business rules. |
| Equifax Audit History | Equifax | Credit Bureau | Equifax Equifax Audit History | /admin/equifax-audit-history | Moved | Every recorded Equifax event in one feed. |
| Equifax Dashboard | Equifax | Credit Bureau | Equifax Equifax Dashboard | /admin/equifax-dashboard | Moved | Equifax operations: paid requests, savings from reuse, failures and items needing review. |
| Equifax Requests | Equifax | Credit Bureau | Equifax Equifax Requests | /admin/equifax-requests | Moved | Every paid Equifax request with its report for authorised staff. |
| Equifax Settings | Equifax | Credit Bureau | Equifax Equifax Settings | /admin/equifax-settings | Moved | Equifax business rules. |
| Experian Audit History | Experian | Credit Bureau | Experian Experian Audit History | /admin/experian-audit-history | Moved | Every recorded Experian event in one feed. |
| Experian Dashboard | Experian | Credit Bureau | Experian Experian Dashboard | /admin/experian-dashboard | Moved | Experian operations: paid requests, savings from reuse, failures and items needing review. |
| Experian Requests | Experian | Credit Bureau | Experian Experian Requests | /admin/experian-requests | Moved | Every paid Experian request with its report for authorised staff. |
| Experian Settings | Experian | Credit Bureau | Experian Experian Settings | /admin/experian-settings | Moved | Experian business rules. |
| Campaigns | Marketing | Email Marketing | Campaigns | /admin/newsletter-campaigns | Moved | Emails sent to subscribers, optionally from an article. |
| Newsletter | Marketing | Email Marketing | Newsletter | /admin/newsletter-dashboard | Moved | Newsletter health: subscribers, growth, sources and best-converting articles. |
| Newsletter Settings | Marketing | Email Marketing | Newsletter Settings | /admin/newsletter-settings | Moved | Sender name/address and double opt-in. |
| Segments | Marketing | Email Marketing | Segments | /admin/newsletter-segments | Moved | Groups of subscribers (by preference/source) a campaign can target. |
| Subscribers | Marketing | Email Marketing | Subscribers | /admin/newsletter-subscribers | Moved | People who joined the mailing list (only through their own signup). |
| Templates | Marketing | Email Marketing | Templates | /admin/newsletter-templates | Moved | Email layouts used by campaigns. |
| — | — | Help & Guides (top level) | Help & Guides | /admin/help | New | How the admin is organised, what each module is for, and step-by-step guides for common jobs. |
| Funnel Analytics | Catalog | Leads & Enquiries | Analytics & LMS Funnel Analytics | /admin/analytics-dashboard | Moved | The online application funnel: journeys started completed eligibility lender chosen submitted. |
| FynnOn LMS Sync | New screen | Leads & Enquiries | Analytics & LMS FynnOn LMS Sync | /admin/fynnon-lms-sync | New | Delivery of new website leads to FynnOn LMS: connection status, what was sent, what failed, and retry. |
| Lead Analytics | New screen | Leads & Enquiries | Analytics & LMS Lead Analytics | /admin/lead-analytics | New | Which pages, forms, products and campaigns bring leads — and how many verify and reach the LMS. |
| Applications | Catalog | Leads & Enquiries | Applications | /admin/journey-sessions | Moved | Online applications in progress: each visitor who pressed "Apply" on a loan page. |
| Credit Score Checks | Catalog | Leads & Enquiries | Credit Score Checks | /admin/credit-score-checks | Moved | Free credit score checks made by visitors on the public credit score pages. |
| Customers | Catalog | Leads & Enquiries | Customers | /admin/customers | Moved | People with a FynnOn customer portal account or a credit-bureau record. |
| Contact Enquiries | Content | Leads & Enquiries | Enquiries | /admin/contact-enquiries | Moved + renamed | Enquiries from the Quick Enquiry box, the /quick-enquiry page, loan-page forms and the contact page. |
| Lead Inbox | New screen | Leads & Enquiries | Lead Inbox | /admin/lead-inbox | New | Every lead from every website form in one list: enquiries, OTP verifications, credit score checks and applications. |
| Loan Applications | Catalog | Leads & Enquiries | Loan Applications | /admin/applications | Moved | Loan applications where the visitor chose a lender, uploaded documents and submitted. |
| Mobile OTP Verifications | Catalog | Leads & Enquiries | Mobile OTP Verifications | /admin/mobile-otp-verifications | Moved | Read-only log of every OTP sent and whether the visitor verified it. Codes are never shown. |
| Document Requirements | Catalog | Loan Products & Lenders | Application setup Document Requirements | /admin/document-requirements | Moved | Which documents each lender offer needs, and whether they are mandatory. |
| Document Types | Catalog | Loan Products & Lenders | Application setup Document Types | /admin/document-types | Moved | The kinds of documents applicants can upload (PAN, payslips, bank statements…). |
| Journeys | Catalog | Loan Products & Lenders | Application setup Journeys | /admin/journey-definitions | Moved | The question flow of the online application for each loan product. |
| Eligibility Rules | Catalog | Loan Products & Lenders | Eligibility Eligibility Rules | /admin/eligibility-rule-sets | Moved | Each lender offer's eligibility rules (age, income, credit score…), versioned with effective dates. |
| Eligibility Tester | Catalog | Loan Products & Lenders | Eligibility Eligibility Tester | /admin/eligibility-tester | Moved | Try a hypothetical applicant and see which lenders would accept them, and why. |
| Employers | Catalog | Loan Products & Lenders | Eligibility Employers | /admin/employers | Moved | Employers and each lender's category for them (used for salaried eligibility). |
| Interest Rates | Catalog | Loan Products & Lenders | Interest Rates | /admin/interest-rates | Moved | Published interest rates per lender offer, with evidence and a draft approve publish workflow. |
| Lender Offers | Catalog | Loan Products & Lenders | Lender Offers | /admin/lender-products | Moved | What each lender offers for each loan product: amounts, tenure, fees and basic eligibility. |
| Lenders | Catalog | Loan Products & Lenders | Lenders | /admin/lenders | Moved | Banks and NBFCs FynnEdge works with: name, logo, type and serviceable locations. |
| Loan Products | Catalog | Loan Products & Lenders | Loan Products | /admin/loan-products | Moved | The loan types FynnEdge offers (Personal, Home, Business Loan…): page content, calculator and rate settings. |
| Loan Landing Page SEO | Content | SEO & Performance | Loan Landing Page SEO | /admin/loan-landing-page-seo | Moved | Search title, description and indexing of each loan landing page. |
| Loan Product SEO | Content | SEO & Performance | Loan Product SEO | /admin/loan-product-seo | Moved | Search title, description and social preview of each loan product page. |
| Missing pages (404) | Website Settings | SEO & Performance | Missing pages (404) | /admin/not-found-urls | Moved | Addresses visitors and crawlers asked for that do not exist (404), with hit counts. |
| Page SEO | Website Settings | SEO & Performance | Page SEO | /admin/page-seos | Moved | Search title, description and social preview for any URL on the site. |
| Redirects | Website Settings | SEO & Performance | Redirects | /admin/redirects | Moved | Send an old or wrong URL to the right page (301) or mark it gone (410). |
| SEO & Tracking | Website Settings | SEO & Performance | SEO & Tracking | /admin/seo-analytics | Moved | Sitewide SEO, indexing, verification, tracking codes (GA4, GTM, Meta…) and cookie consent. |
| Schema Templates | Content | SEO & Performance | Schema Templates | /admin/schema-templates | Moved | Reusable JSON-LD blocks that page SEO sections can apply. |
| Structured Data | Website Settings | SEO & Performance | Structured Data | /admin/structured-data | Moved | The shape of the sitewide JSON-LD (Organization/WebSite/WebPage types). |
| Create a Page | New screen | Website Pages | Create a Page | /admin/create-a-page | New | Not sure which kind of page to make? Pick what you want to publish and this guide opens the right editor. |
| Calculator Pages | Content | Website Pages | Fixed pages Calculator Pages | /admin/calculator-pages | Moved | The "About this calculator" text and SEO for each calculator page. |
| Credit Score Pages | Content | Website Pages | Fixed pages Credit Score Pages | /admin/credit-score-pages | Moved | Long-form content below each bureau's free credit score page. |
| Pages | Content | Website Pages | General Pages | /admin/pages | Moved + renamed | General information pages: About, Careers, FAQs, legal pages, and any new page you add at fynnedge.com/{address}. |
| Banners | Content | Website Pages | Homepage & sections Banners | /admin/banners | Moved | The homepage hero carousel slides. |
| Navigation Links | Content | Website Pages | Homepage & sections Footer Quick Links | /admin/navigation-links | Moved + renamed | Extra links in the footer's "Quick Links" column. |
| Marketing Sections | Content | Website Pages | Homepage & sections Marketing Sections | /admin/marketing-sections | Moved | Wording of fixed sections (homepage call-to-actions, enquiry form headings…). |
| Promo bars | Content | Website Pages | Homepage & sections Promo bars | /admin/promo-bars | Moved | Sticky offer bars that slide up from the bottom of chosen pages. |
| Loan Landing Page Content | Content | Website Pages | Loan Landing Page Content | /admin/loan-landing-page-content | Moved | Edit only the marketing text of an existing landing page (no URLs, products or amounts). |
| Loan Landing Pages | Content | Website Pages | Loan Landing Pages | /admin/loan-landing-pages | Moved | Lead-generating pages for one loan product, by amount, type, need or city (e.g. "Personal Loan in Pune"). |
| Loan Product Content | Content | Website Pages | Loan Product Content | /admin/loan-product-content | Moved | Edit only the marketing text of a loan product page: headline, features, benefits, documents, steps. |
| About Page | Website Settings | Website Settings | About Page | /admin/about-page-settings | Moved | Wording of the About page sections. |
| Calculators Page | Website Settings | Website Settings | Calculators Page | /admin/calculator-pages-settings | Moved | Headings of the /calculators page and each calculator page. |
| Grievance Redressal Matrix | Content | Website Settings | Company information Grievance Redressal Matrix | /admin/grievance-levels | Moved | The grievance redressal matrix (levels, contacts, turnaround times). |
| How It Works Steps | Content | Website Settings | Company information How It Works Steps | /admin/how-it-works-steps | Moved | Steps of the "How it works" section. |
| Job Openings | Content | Website Settings | Company information Job Openings | /admin/job-openings | Moved | Open positions listed on the careers page. |
| Credit Score Page | Website Settings | Website Settings | Credit Score Page | /admin/credit-score-page-settings | Moved | Wording of each bureau's free credit score page. |
| Settings | Website Settings | Website Settings | General Settings | /admin/settings | Moved + renamed | Site-wide settings: business profile, contact details, homepage hero, appearance and more. |
| Quick Enquiry Page | Website Settings | Website Settings | Quick Enquiry Page | /admin/quick-enquiry-page-settings | Moved | Wording of the /quick-enquiry page (used for ads). |

*83 rows: 76 existing screens moved (4 also renamed), 7 new screens. Within Credit Bureau the old group "Credit Bureau" (CRIF) is now the "CRIF" tab, next to the CIBIL, Experian and Equifax tabs.*

## D. Implemented features

1. **Navigation:** `app/Filament/Clusters/*` (9 modules), the central map `AdminModules`, trait `BelongsToAdminModule`; URL-preserving `AdminModuleCluster`; collapsible sidebar; module-aware breadcrumbs; roles see only modules they can open.
2. **Help system:** `app/Support/Admin/{AdminGuide,AdminRequirements,AdminWorkflows,AdminLinks,AdminHelpPanels}` with three render hooks, plus the **Help & Guides** page (`/admin/help`), which has a searchable module map and 6 workflow diagrams (launch a loan product, create a page, publish an article, how a lead travels, send a newsletter, fix a broken link).
3. **Lead Inbox** (`/admin/lead-inbox`): read-only UNION over the 5 lead tables; filters (source, date range, product, verified, LMS status, page, campaign, repeat enquiries); "View journey" (exact mobile-number match only); "Send to LMS"; CSV export.
4. **FynnOn LMS** (`app/Modules/FynnOn`): settings, payload builder, HTTP client, queued job, event hooks, alert email, `fynnon:send-pending` command, and the **FynnOn LMS Sync** screen.
5. **Lead Analytics** (`/admin/lead-analytics`) and **page-view tracking** (`RecordPageView` middleware).
6. **Create a Page** (`/admin/create-a-page`) with 4 page types, placement and indexing explanations, live prerequisites, type-specific steps and a direct link to the right editor (campaign pages open with "noindex" pre-selected). "Page types guide" buttons on the Pages, Landing Pages and Articles lists.
7. **General pages:** public catch-all route `/{address}` (registered last), slug validation against existing routes and redirects, preview links, sitemap entries; Pages list shows type, SEO completeness, last update, View on website, Publish and Unpublish.
8. **Authors** (`/admin/authors`), author picker in the article editor, Author column in the articles list, byline and author box on articles and cards, author page `/resources/authors/{name}`, Person JSON-LD, Media Library tracking of author photos, Hindi translations of the new site text.
9. **Dashboard:** welcome card with shortcuts and the module map, lead stats, 30-day lead chart, latest leads, interest rates (existing), content status, recently updated content.
10. **Theme:** module tabs above the title, quiet sidebar headings, active-item accent, rounded surfaces, table headers, print styles. Checked at 1440 px, 820 px (tablet) and 390 px (phone).

## E. Lead management

Each source keeps its own table and screen. The Lead Inbox projects them onto common columns:

| Source | Table | Page recorded? | Campaign (UTM)? | Verified |
|---|---|---|---|---|
| Enquiry (contact, Quick Enquiry, loan-page, /quick-enquiry) | `contact_enquiries` | Yes (`source_url`) | No | `phone_verified_at` |
| Credit score check | `credit_score_checks` | No — shown as "Not recorded" | No | `mobile_verified_at` |
| Online application (with a phone) | `journey_sessions` | Yes (landing page / referrer) | Yes | `phone_verified_at` |
| Loan application | `applications` (+ journey, customer) | Via its journey | Via its journey | Via its journey |
| OTP verification (portal sign-ins excluded) | `mobile_otp_challenges` | No | No | `verified_at` |

Duplicate handling is the existing rule: the same number and the same product are merged into one enquiry, with a repeat count. The Inbox shows the repeat count, and a filter lists repeat enquiries. Records are only linked to one person by an **exact mobile-number match**. Newsletter subscribers are **not** treated as leads, because they consented to a mailing list, not to being contacted about a loan. Each user sees only the sources whose own screens they can open; OTP codes, PAN and credit reports never appear.

## F. FynnOn integration

- **Existing integration found:** only the *reverse* direction (the LMS calling FynnEdge via API Clients). There was no outbound push, so one was built.
- **Trigger:** enquiry created or reopened; score check created; online application mobile verified; loan application submitted; OTP verified. The push is a queued job dispatched after commit, so it never slows or breaks a visitor's form.
- **Request:** `POST FYNNON_LMS_URL`, JSON, with `Authorization: Bearer`, `Idempotency-Key`, `X-FynnEdge-Event`, `X-FynnEdge-Timestamp` and an optional HMAC `X-FynnEdge-Signature`. The payload carries contact, loan, form, attribution and status only. It never includes PAN, date of birth, score, report contents or OTP data.
- **Success** is counted only on a 2xx reply that acknowledges the lead (`reference`/`id`/`lead_id`/`success:true`), or on a 409 (already held).
- **Retries:** timeout, 429 and 5xx are retried after 1 min, 5 min, 15 min and 1 h (5 attempts). After that, or at once for 401/422-type errors, the lead is marked "Failed — needs retry" and an alert email is sent.
- **Duplicate prevention:** unique queued job per lead and event; skipped if the LMS already confirmed it; a stable reference and Idempotency-Key.
- **Log:** one `audit_logs` row per attempt on the lead (no contact data), shown on the FynnOn LMS Sync screen and in the Inbox.
- **End-to-end test status:** verified against a simulated LMS in automated tests (success, malformed 2xx, 409, 503 retry, 500 and 422 give-up, idempotency, resend, eligibility, payload privacy). **Not tested against the real FynnOn LMS:** no endpoint, key or payload contract was available. It stays off until configured (see L).

## G. Analytics

Data sources: the 5 lead tables, `mobile_otp_challenges` and **new page-view counts** stored in the existing `analytics_events` table (`event_key = page_view`).

Page views are recorded **without cookies, IP addresses or user agents**. Bots, prefetches, signed previews, admin and portal pages are skipped, and rows are pruned after 400 days. There is therefore **no "unique visitors" figure**, by design. Conversion rates appear only for pages and periods where views were recorded (tracking starts from deployment). Campaign figures exist only for applications and page views, because enquiry forms do not store UTM tags.

## H. Page management

| Type | Where it lives | Use it for | Needs first |
|---|---|---|---|
| General page | `fynnedge.com/{address}` | Information pages, policies, guides; no form or rates | — |
| Loan landing page | `fynnedge.com/loans/{product}/{address}`, listed in the Loans menu | Search and lead pages for one loan product, with enquiry form, rates and calculator | A loan product |
| Campaign landing page | A loan landing page with "noindex" | Ad traffic; tag links with `utm_*` and track them in Lead Analytics | A loan product |
| Blog article | `fynnedge.com/resources/{address}` | Editorial content with author byline | An author (recommended) |

Footer placement uses **Footer Quick Links**. The header menus and the footer's Company/Legal columns are fixed in code, as before.

## I. Author management

Authors (name, photo, designation, bio, https profile link, active flag) are managed under Blog & Content → Authors and picked in the article editor. Articles show a byline with photo, an "About the author" box and a link to the author page, plus `author` as a schema.org Person. Articles without an author keep the previous Organization author. Deactivating an author hides them publicly but keeps the link.

**Storage (updated 24 Nov 2026, after your approval):** authors now live in a dedicated `authors` table, and articles link to them through `articles.author_id`. If an author is deleted, their articles stay and simply lose the byline. Three additive, reversible migrations:

- `2026_11_24_130249_create_authors_table`: name, slug (the author page address), designation and bio (each with a Hindi column), photo path, https profile link, active flag, order.
- `2026_11_24_130251_add_author_id_to_articles_table`: nullable foreign key, set to null if the author is deleted.
- `2026_11_24_130252_move_blog_author_settings_to_authors_table`: copies the interim `blog_authors` / `blog_article_authors` settings. Each legacy ID becomes the author's `public_id`, and each slug, photo path and active state is kept. It is safe to run twice. The two settings are **kept** until production is verified; rolling back removes only what was copied.

Admin: Blog & Content → Authors (same address, `/admin/authors`) is now a normal list/create/edit screen with article counts. Access follows the Article permissions, so no role changes are needed. The interim settings-based screen and service were removed.

## J. Database protection report

**Update, 24 Nov 2026:** after your approval, the authors table was added (see I). On the local MySQL, all 4 migrations that were pending (the 3 author migrations plus the repository's existing `2026_11_12_090432_move_about_us_redirect_to_redirects_table`) were applied in batch 67 by a plain `php artisan migrate` from outside this session. Compared with the baseline below, the only differences are: the new `authors` table (0 rows), `articles.author_id` (all null, because there were no legacy author settings), `migrations` +4, `redirects` +1 and `audit_logs` +1 (both from the about-us redirect migration), and `sessions` +1 (normal use). Reversibility was proven on MySQL: rolling back the 3 author migrations returned the schema to the exact baseline checksum below, and re-applying them restored it.

The baseline below describes the original no-schema-change phase:

Evidence was taken from the development MySQL database (`fynnedge`) with read-only `information_schema` queries before any change and again after all work.

| Check | Before | After |
|---|---|---|
| Tables | 84 | 84 |
| Columns | 1,223 | 1,223 |
| Foreign keys | 90 | 90 |
| Index entries | 374 | 374 |
| Migrations (count / last id) | 153 / 177 | 153 / 177 |
| Full column dump (name, type, nullability, default) MD5 | `14399496b9905f9c54cfe5dc22126dfc` | `14399496b9905f9c54cfe5dc22126dfc` (identical) |
| Row count of every one of the 84 tables | snapshot | identical for all 84 |

- No tables created or deleted; no columns added or removed; no schema modifications; no migration files added.
- No existing records were modified or deleted. Tests ran on in-memory SQLite. Screenshots used a throwaway SQLite file, deleted afterwards.
- After deployment the application **writes new rows** in its normal operation: `analytics_events` (page views), `audit_logs` (LMS delivery attempts), `authors`, `jobs` (LMS queue). Only page-view rows older than 400 days are ever pruned.

## K. Test results

Pest, run on in-memory SQLite (`php artisan test --compact`). The baseline was run on an untouched checkout of the same commit, so pre-existing results are not mixed with new ones.

| Run | Total | Passed | Failed | Skipped | Assertions |
|---|---:|---:|---:|---:|---:|
| Baseline (before any change) | 2,836 | 2,836 | 0 | 0 | 12,271 |
| First full run after implementation | 2,980 | 2,975 | 5 | 0 | — |
| **Final full run** | **2,982** | **2,982** | **0** | **0** | **12,632** |

The 5 failures in the first run were real regressions from the new `/{address}` route: unknown trailing-slash URLs got a 301 then a 404, and one preview test assumed new pages have no public URL. They were fixed in `UrlPath::isRoutable()` (the catch-all counts only for a published page). One existing test (`PagePreviewTest`) was updated for the intended behaviour change, and a regression test was added.

New tests (146 cases including datasets):

| Category | File | Cases |
|---|---|---:|
| Module / navigation (every screen opens for a super admin, URLs unchanged, role visibility) | `Filament/AdminNavigationTest` | 93 |
| Help system, workflows, dashboard | `Filament/AdminHelpTest` | 6 |
| Page creation, general pages, slug safety, campaign noindex, publish/unpublish, slash redirect | `Filament/CreateWebsitePageTest` | 10 |
| New general page preview via signed link | `ContentTeam/PagePreviewTest` (existing file) | 1 |
| Lead Inbox (sources, permissions, filters, journey, CSV) | `Leads/LeadInboxTest` | 7 |
| Analytics and page-view tracking (privacy, bots, pruning, comparison) | `Leads/LeadAnalyticsTest` | 9 |
| FynnOn LMS (success, malformed 2xx, 409, retry, give-up and alert, idempotency, eligibility, payload privacy, access, backlog) | `FynnOn/FynnOnSyncTest` | 14 |
| Authors (upload, validation, assignment, byline, JSON-LD, author page, media library) | `Articles/ArticleAuthorsTest` | 6 |

Security coverage in the new tests: role-based access to every new screen, per-source lead visibility, no PAN/score/OTP data in the LMS payload or logs, https-only author links, slug clash validation, and CSV formula neutralising. Other checks: Laravel Pint (clean), `npm run build` (passes), route list (all 289 pre-existing routes identical; 9 module routes, 1 author route and the general-page route added), config validation. No static-analysis tool is configured in this project.

Responsive check: screenshots at 1440 px, 1280 px, 820 px and 390 px using headless Chromium against a throwaway SQLite copy (dashboard, Lead Inbox, Create a Page, Lead Analytics, FynnOn Sync, Help, public article, author page, new general page).

## L. Outstanding items

1. **FynnOn LMS connection (input needed):** set `FYNNON_LMS_URL`, `FYNNON_LMS_TOKEN` (and optionally `FYNNON_LMS_SIGNING_SECRET`, `FYNNON_LMS_ALERT_EMAIL`, `FYNNON_LMS_SOURCES`) on the server. The FynnOn team should confirm or adapt to the payload contract shown on the FynnOn LMS Sync screen. Then send the backlog with `php artisan fynnon:send-pending --since=YYYY-MM-DD` or the "Send undelivered leads" button. A queue worker must be running (it already is for other jobs).
2. **Schema-dependent items (approval needed):** UTM capture on enquiry forms (needs columns on `contact_enquiries`); page attribution for credit score checks; a dedicated LMS sync table if you want per-lead LMS IDs queryable in SQL.
3. **Not available by design:** unique visitors (no visitor identifier is stored); header menu editing (header is hardcoded).
4. **Deployment:** run `npm run build`; deploy is not automatic. Existing admins may need to click "Reset" in a table's column manager to see newly added columns (Filament remembers column layouts per session).
5. **Campaign pages** still appear in the Loans menu under their group while published, like every landing page.

## M. Final status

**COMPLETED WITH DOCUMENTED LIMITATIONS**: everything that could be built without schema changes or external credentials is implemented and tested. The FynnOn push is complete but inactive until the LMS details are supplied.
