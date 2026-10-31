# Search Console "Not found (404)" report, October 2026

Audit, fixes and prevention for the 32 URLs in `not-found404.xlsx` (sheet
"Table", Search Console → Page indexing → Not found (404), sitemap filter "All
known pages", last crawled 2026-07-06 to 2026-10-24). The per-URL audit, with
every column, is in [gsc-not-found-audit-2026-10.csv](gsc-not-found-audit-2026-10.csv).
How the rules work day to day is in [url-architecture.md](url-architecture.md).

Status: **implemented and verified locally, not deployed.** Nothing has been
committed, pushed or deployed, and nothing on the live site has changed.

## 1. Executive summary

| | URLs |
| --- | ---: |
| In the report (all 32 rows parsed, none skipped) | 32 |
| Redirected (301, one hop, to a live equivalent page) | 19 |
| Restored (page existed but its route was missing) | 0 |
| Marked Gone (410) | 9 |
| Intentionally left as 404 | 4 |
| Still waiting on a business decision | 0 |
| Needing production infrastructure changes to fix | 0 |

Every URL falls in exactly one of redirected, Gone or 404 (19 + 9 + 4 = 32).
The business made the judgement calls on 2026-10-31: five retired articles
redirect to their topic page, `/team/` and `/gallery/` go to About, the other
retired articles are Gone, and `/mutual-fund/`, `/debit-card/` and `/review/`
stay 404. All 32 need only the normal deploy plus `php artisan migrate`.

Verification so far:

- **Live, before the change (2026-10-31):** all 32 answer 404 in one response.
  None redirects, none is in the live sitemap (495 URLs), and no page on the
  live site links to any of them (all 495 sitemap pages crawled).
- **Every destination, live (2026-10-31):** each of the 13 distinct targets
  answers 200 in one hop, with a canonical pointing at itself and
  `index, follow`.
- **Local, production mode (`APP_ENV=production`, `APP_DEBUG=false`):** all 32
  answer as intended (table below). No error page shows debug output.
- **Automated tests:** every URL is covered by a test (section 6).
- **Live, after the change:** not done. It needs the deploy.

## 2. URL-by-URL results

"Live before" is the live site on 2026-10-31. "Local" is the production-mode
check on 2026-10-31. Every row is also pinned by an automated test in
`tests/Feature/RedirectsTest.php`. None is verified live after deploy yet.

| # | Original URL | Original problem | Action taken | Destination | Expected | Verification |
| ---: | --- | --- | --- | --- | --- | --- |
| 1 | `/mutual-fund/` | Old WordPress service page; no such service | Left as 404 (business decision) | — | 404 | Live before 404 · Local 404, branded page |
| 2 | `/the-home-loan-advantage/` | Retired WordPress article; no equivalent | 410 Gone row | — | 410 | Live before 404 · Local 410 |
| 3 | `/how-to-combine-multiple-loans-and-reduce-your-emi-burden-in-india-in-2026/` | Retired article | 301 row (business approved) | `/loans/personal-loans/personal-loan-for-debt-consolidation` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 4 | `/debit-card/` | Old service page; no such service | Left as 404 (business decision) | — | 404 | Live before 404 · Local 404 |
| 5 | `/home-loan/` | Old WordPress service page | 301 row | `/loans/home-loan` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 6 | `/taking-out-a-personal-loan-on-probation-is-it-even-an-option/` | Retired article; no equivalent | 410 Gone row | — | 410 | Live before 404 · Local 410 |
| 7 | `/tips-for-using-a-personal-loan-for-your-dream-holiday/` | Retired article | 301 row (business approved) | `/loans/personal-loans/personal-loan-for-travel` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 8 | `/unsecured-business-loans-for-startups-a-2026-guide-to-capital-without-collateral/` | Retired article | 301 row (business approved) | `/loans/business-loan` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 9 | `/the-personal-loan-master-guide/` | Retired article; no single equivalent | 410 Gone row | — | 410 | Live before 404 · Local 410 |
| 10 | `/cibil-score/` | Old WordPress page | 301 row | `/credit-score/cibil` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 11 | `/are-self-employed-people-eligible-for-a-personal-loan-without-an-itr/` | Retired article | 301 row (business approved) | `/loans/personal-loans/personal-loan-for-self-employed` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 12 | `/team/` | Old company page | 301 row (business approved) | `/about` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 13 | `/the-msme-growth-manual/` | Retired article; no equivalent | 410 Gone row | — | 410 | Live before 404 · Local 410 |
| 14 | `/the-credit-score-recovery-roadmap/` | Retired article; no equivalent | 410 Gone row | — | 410 | Live before 404 · Local 410 |
| 15 | `/comments/feed/` | WordPress comment feed | 410 Gone row | — | 410 | Live before 404 · Local 410 |
| 16 | `/car-loan-calculator-2/` | Duplicate WordPress calculator slug | 301 row | `/calculators/emi/car-loan` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 17 | `/how-loan-prepayment-can-save-you-lakhs-in-interest-in-india-in-2026/` | Retired article; no single equivalent | 410 Gone row | — | 410 | Live before 404 · Local 410 |
| 18 | `/personal-loan-calculator/` | Old calculator page | 301 row | `/calculators/emi/personal-loan` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 19 | `/personal-loan/` | Old service page | 301 row | `/loans/personal-loans` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 20 | `/overdraft-facilities/` | Old service page | 301 row | `/loans/flexi-hybrid-term-loan` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 21 | `/how-to-manage-your-emis-smartly-in-2026-and-stay-financially-stress-free/` | Retired article; no equivalent | 410 Gone row | — | 410 | Live before 404 · Local 410 |
| 22 | `/2026/03/03/` | WordPress date archive | 410 Gone row | — | 410 | Live before 404 · Local 410 |
| 23 | `/business-loan/` | Old service page | 301 row | `/loans/business-loan` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 24 | `/career/` | Old company page | 301 row | `/careers` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 25 | `/gallery/` | Old company page | 301 row (business approved) | `/about` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 26 | `/review/` | Old page; no reviews page | Left as 404 (business decision) | — | 404 | Live before 404 · Local 404 |
| 27 | `/personal-loan-calculator-2/` | Duplicate WordPress calculator slug | 301 row | `/calculators/emi/personal-loan` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 28 | `/contact-us/` | Old company page | 301 row | `/contact` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 29 | `/home-loan` | Slashless variant of #5 | Same row as #5 | `/loans/home-loan` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 30 | `/personal-loan` | Slashless variant of #19 | Same row as #19 | `/loans/personal-loans` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 31 | `/best-personal-loan-in-india-2026-a-definitive-ranking-of-top-banks-and-nbfcs/` | Retired article | 301 row (business approved) | `/loans/personal-loans` | 301 → 200 | Live before 404 · Local 301 · Destination live 200 |
| 32 | `/lander` | Domain-parking path from before the site launched | Left as 404 | — | 404 | Live before 404 · Local 404 |

All URLs are on `https://fynnedge.com`. Query strings carry across the
redirect (checked: `/personal-loan/?utm_source=gsc` → `/loans/personal-loans?utm_source=gsc`).

## 3. Root causes

Confirmed by the investigation:

- **The WordPress site was replaced without a redirect map for these URLs.**
  31 of the 32 are pages, articles or generated URLs of the old WordPress site.
  The Laravel codebase and its git history contain no WordPress content, and
  none of the paths exists in Laravel. Google still remembers and recrawls them
  ("All known pages" covers URLs discovered anywhere, not only in the sitemap).
- **Nothing on the current site causes them.** No live page links to any of
  the 32 (a crawl of all 495 sitemap pages), and none is in the sitemap.
- **Not a routing or trailing-slash fault.** Each URL answered 404 in one
  response. The slash handling from the earlier "Page with redirect" work is
  already live (`/business-loan-3/` takes one 301), so no redirect led into a
  404 here.
- **No content was lost from this site.** No article with any of these slugs
  exists live under `/resources/` (all 13 slugs checked), so there was nothing
  to restore.

Probable, not proven:

- `/lander` was last crawled 2026-07-06, before the Laravel site went live.
  `/lander` is the path domain-parking services use, so it was most likely the
  parked domain's landing page, never FynnEdge content.
- What each old page contained is inferred from its slug and the live
  replacement page. The Internet Archive was offline during the audit, so old
  copies could not be checked.

## 4. Other problems found and fixed

- **The 404 page was Laravel's bare "404 | Not Found"**, with no branding and
  no way back into the site. It now uses the site layout, with links to loans,
  calculators, resources and contact (`resources/views/errors/404.blade.php`).
  It is `noindex`, keeps status 404, never redirects, and shows no exception
  details even with debug on. Scanner probes and stale asset URLs still get the
  bare page, so they cost no database work.
- **The Car Loan EMI calculator was orphaned.** `/calculators/emi/car-loan` is
  live and indexable, but `CalculatorCatalog` did not list it, so it was in
  neither the sitemap nor the `/calculators` directory nor the header menu.
  It is now listed, since `/car-loan-calculator-2/` redirects to it.
- **The sitemap could list a redirecting page.** A published record whose URL
  also had a redirect row was still listed. Entries with an active redirect or
  Gone rule are now dropped.
- **There was no 404 monitoring.** See section 8.

Found, but needing an admin change in production (content is in the
production database, which this work cannot reach):

| Live page | Broken link | Suggested fix |
| --- | --- | --- |
| `/resources/how-businesses-in-noida-can-opt-for-working-capital-term-loans-or-overdrafts` | `<a href="instant personal loan">`: link text typed in place of the URL, resolves to a 404 | Point it at `/loans/personal-loans` |
| same article | `<a href="EMI calculator personal loan">`: same mistake | Point it at `/calculators/emi/personal-loan` |
| `/resources/factors-affecting-personal-loan-eligibility-in-india` | `https://fynnedge.com/why-your-loan-gets-rejected-and-how-to-fix-it-in-2026/`: an old WordPress article, 404 | Editorial call: link `/resources/improve-loan-approval-chances` instead, or remove it. That old URL is not in this report yet but will be; add a rule for it once its replacement is decided. |

`seo:audit` now reports all three kinds of link.

## 5. Files changed

Redirect registry (existing system, extended rather than replaced):

- `app/Models/Redirect.php`: 410 Gone rows, notes, audit History, and chain
  resolution that ends at a Gone row.
- `app/Http/Middleware/HandleRedirects.php`: serves Gone rows as 410 in the
  visitor's language; route matching moved to `UrlPath`.
- `app/Http/Middleware/SetSiteLocale.php`: `apply()` for responses built
  before it runs.
- `app/Support/UrlPath.php`: `isRoutable()`, shared by the middleware and the form.
- `app/Filament/Resources/Redirects/Schemas/RedirectForm.php`: Gone type,
  notes, and refusal of chains, Gone destinations, unroutable paths and
  non-http(s) URLs. Prefills the old path from Missing pages.
- `app/Filament/Resources/Redirects/Tables/RedirectsTable.php`,
  `RedirectResource.php`: Gone badge and filter, notes column, History tab.
- `database/factories/RedirectFactory.php`: `gone()` state.

404 page and monitoring:

- `resources/views/errors/404.blade.php`, `410.blade.php`, `bare-404.blade.php`,
  `resources/views/components/site/missing-page.blade.php`: error pages.
- `bootstrap/app.php`: registers `RecordNotFoundRequests`; bare 404 for noise.
- `app/Providers/AppServiceProvider.php`: an empty `$errors` bag for the
  layout outside the `web` group (the footer form reads it).
- `app/Http/Middleware/RecordNotFoundRequests.php`,
  `app/Support/NotFound/NotFoundRequests.php`, `app/Models/NotFoundUrl.php`,
  `database/factories/NotFoundUrlFactory.php`: aggregated 404 counting.
- `app/Filament/Resources/NotFoundUrls/**`, `app/Policies/NotFoundUrlPolicy.php`:
  Admin → Website Settings → Missing pages (404).
- `app/Console/Commands/SeoNotFoundReportCommand.php`: `php artisan seo:not-found`.
- `app/Console/Commands/SeoAuditCommand.php`: checks Gone rows, resolves
  relative links, requests each non-sitemap internal link once (bounded by
  `--link-limit`, never an action URL robots.txt disallows), and flags links
  with spaces in the href or to a Gone page.
- `routes/console.php`: daily prune at 03:10.
- `config/seo.php`, `.env.example`: `SEO_NOT_FOUND_RETENTION_DAYS`,
  `SEO_NOT_FOUND_MAX_PATHS`.

Sitemap and calculators:

- `app/Support/Seo/Sitemap.php`: drops URLs with an active redirect or Gone rule.
- `app/Support/Calculators/CalculatorCatalog.php`: Car Loan EMI calculator.
- `lang/hi.json`: Hindi for every new visitor string.

Docs, rules and tests:

- `docs/seo/gsc-not-found-audit-2026-10.csv`, this report, and
  `docs/seo/url-architecture.md` (Gone rows, monitoring, deploy, rollback).
- `.ai/rules/redirects-migrations.md`, `.ai/rules/errors.md`, `.ai/rules/tests.md`.
- New tests: `tests/Feature/NotFoundPageTest.php`, `RecordNotFoundRequestsTest.php`,
  `SeoNotFoundReportCommandTest.php`, `Filament/NotFoundUrlResourceTest.php`.
  Extended: `RedirectsTest.php`, `SeoAuditCommandTest.php`, `SitemapTest.php`.
  Adjusted, without weakening what they check:
  - `CalculatorsMegaMenuTest.php` seeds a car loan product for the new catalog entry.
  - One assertion in `SiteLanguageTest.php` now matches `<html lang="hi"`
    without the closing `>`, since the 404 page's `<html>` tag carries a class.
  - `ContentCache/ContentCacheCommandsTest.php` (2 tests) compares the cache
    versions before and after, instead of expecting 0. The new data migration
    moves the Redirects version, which it must do so the new rows apply at once.
  - `Filament/AdminActivityTest.php` ("filters activity by action") and
    `Filament/RedirectResourceTest.php` ("lists redirects") clear the rows
    the migrations ship before arranging their own. Redirect rows are now
    audited, and there are now 28 shipped rows, more than one page of the list.

## 6. Database changes

| Migration | Change | Rollback |
| --- | --- | --- |
| `2026_10_31_084214_add_gone_rules_and_notes_to_redirects_table` | `redirects.destination` nullable; adds `redirects.notes` | Deletes every Gone row, drops `notes`, makes `destination` required again |
| `2026_10_31_084215_add_october_2026_not_found_legacy_urls` | 17 redirect rows and 9 Gone rows, each with a note. Skips any path that already has a row (an admin's row wins) | Removes only rows still exactly as shipped |
| `2026_10_31_084217_create_not_found_urls_table` | New aggregated 404 table | Drops it |

`php artisan migrate:rollback --step=3` undoes all three. No existing data is
changed or deleted on the way up. The redirect cache refreshes automatically.

## 7. Tests

Commands run on 2026-10-31:

| Command | Result |
| --- | --- |
| `php artisan test --compact` (complete suite, after the fixes below) | **2762 passed, 0 failed** (11976 assertions) |
| First complete run, before those fixes | 2758 passed, 4 failed. All 4 were caused by this work (the new data migration's rows in every test database) and fixed as listed in section 5. None was pre-existing |
| Targeted run: redirects, 404/410 pages, monitoring, Missing pages admin, `seo:not-found`, `seo:audit`, sitemap, language, calculators, error handling | 183 passed |
| `vendor/bin/pint --dirty --format agent` | passed |

New and extended tests cover:

- **Routing and status codes:** all 32 report URLs (19 × one-hop 301 to a
  routable destination, 9 × 410, 4 × 404), with their trailing slashes, as
  Google requests them.
- **Redirects:** Gone rows answer 410, from slash variants too, and count
  hits. A chain ending at a Gone row answers 410. The form saves a Gone rule
  without a destination, drops the destination when a row becomes Gone, and
  refuses chains, Gone destinations, unroutable paths and non-http(s) URLs
  with their messages. Changes are recorded in History. The migration leaves
  an admin's row alone, both up and down. Existing tests still cover loops,
  duplicates, query strings and one-hop chains.
- **Error pages:** the branded 404 keeps status 404, is `noindex`, links the
  main sections, and shows no exception text or file path with debug on.
  Probes and stale assets get the bare 404. JSON requests stay JSON. The 410
  page renders in Hindi when Hindi is chosen.
- **Monitoring:** one row per normalised path with no query string, internal
  vs external referrer, crawler counting, route-bound missing records,
  exclusion of probes, assets, token URLs, POSTs and `seo:audit`, no recording
  of redirects, 410s or 200s, the path cap, and retention pruning.
- **Admin and CLI:** list order, the "linked from this site" filter, the "Add
  rule" link and its hiding, the refusal for a role without permission, and
  `seo:not-found` exit codes and filtering.
- **SEO:** the sitemap drops pages with a redirect or Gone rule. `seo:audit`
  checks Gone rows, requests other internal links once, and flags 404 links,
  hrefs with spaces and links to Gone pages. Existing tests still cover
  sitemap XML, canonicals and robots.txt.

Local production-mode check: `APP_ENV=production`, `APP_DEBUG=false`, against
a throwaway SQLite database with every migration run. All 32 URLs answered as
in section 2, with no debug output. A probe got the bare 404, not the branded page,
and was not recorded. `HEAD` on a Gone URL answered 410.

Not done: a live check after deploy, and `seo:audit` against production. Run
on the server, it reads the production redirect table, which this work cannot see.

## 8. Remaining production work

Deploy (repository changes are uncommitted):

1. Review, commit and deploy this branch.
2. `php artisan migrate --force`, then `php artisan optimize:clear` as usual.
3. Make sure the Laravel scheduler runs (`* * * * * php artisan schedule:run`),
   or Missing pages is never pruned. The newsletter and bureau jobs already need it.
4. Run `php artisan seo:audit --base-url=https://fynnedge.com` on the server
   and the curl checks in [url-architecture.md](url-architecture.md#post-deployment-smoke-test).
   This is the live verification none of the 32 has had yet.
5. Optional alert: a daily cron for `php artisan seo:not-found` with `MAILTO`
   set. It exits non-zero when the site links to a missing page.

Admin content updates: the three broken article links in section 4.

Access control, which is a business decision and has not been changed:

- `super_admin` sees Missing pages (404) at once. Other roles need its
  permissions created on the server
  (`php artisan shield:generate --resource=NotFoundUrlResource --panel=admin`)
  and granted under Access Control → Roles.
- `database/seeders/RoleSeeder.php` says the SEO role "owns" Redirects, but it
  grants only Page SEO. Decide whether the SEO role should get the Redirect
  and Missing pages permissions.

Carried over from the "Page with redirect" work and still open on the live
site on 2026-10-31: production still serves the static `public/robots.txt`
(its header says to delete it after the next deploy), and the Cloudflare
`www` redirect rule. Neither affects these 32 URLs.

Search Console (needs an owner):

- URL Inspection → Test live URL on `/personal-loan/` (301),
  `/comments/feed/` (410) and `/mutual-fund/` (404).
- Page indexing → Not found (404) → **Validate fix** once the live checks
  pass. The 19 redirected URLs will move to "Page with redirect", which is
  correct for them. Gone and 404 URLs drop out as Google recrawls them, over
  weeks rather than days. Search Console offers no way to force that.

## 9. Prevention plan

- **See 404s as they happen, not weeks later.** Every genuine 404 is counted
  per path in Admin → Missing pages (404), most requested first, with filters
  for "linked from this site", "requested by search crawlers" and "first seen
  in the last 7 days". "Add rule" opens the redirect form prefilled. It stores
  no visitor data, is capped at 5000 paths and keeps 90 days.
- **Catch broken links before Google does.** `seo:audit` (post-deploy) now
  requests every internal link that is not a sitemap page, once, and flags
  404s, links through redirects or Gone rows, and hrefs with spaces in them.
  `seo:not-found` fails when the site links to a missing page.
- **Stop bad rules at save time.** The redirect form refuses loops, chains,
  Gone destinations, paths no route answers, non-http(s) URLs and duplicate
  sources, and every change is kept in History. At request time, chains are
  still flattened to one hop and loops still serve the page.
- **Keep the sitemap honest.** It now drops any URL with a redirect or Gone
  rule, on top of the existing noindex, canonical and published-only rules.
  `seo:audit` still checks every sitemap URL live.
- **The decision rule for an old URL** (url-architecture.md): a 301 only to a
  genuinely equivalent page, 410 when the content was removed for good, 404
  when nothing replaces it but the URL may come back. Never the homepage.
- **Regression tests** pin all 32 outcomes, the 404/410 pages, the monitor's
  privacy and limits, the form's refusals and the new audit checks.
