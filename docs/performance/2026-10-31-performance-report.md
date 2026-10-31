# Website performance audit and optimisation, 31 October 2026

Baseline measurements of https://fynnedge.com, the bottlenecks they show, the
code changes made for them, and what production still needs. Raw medians are in
[lighthouse-2026-10-31-production-baseline.tsv](lighthouse-2026-10-31-production-baseline.tsv)
and [lighthouse-2026-10-31-local-before-after.tsv](lighthouse-2026-10-31-local-before-after.tsv).

Status: **local changes tested; deployment awaiting approval.** Nothing has been
committed, pushed or deployed, and nothing on the live site has changed. No
production access was available from this work (no SSH host is configured), so
every production step below is for the server administrator.

## A. Executive summary

**The two things that matter most are on the server, not in the code.**

1. **Production runs with `APP_DEBUG=true`.** It serves Livewire's unminified
   590 KB `livewire.js`, which Livewire only returns when `config('app.debug')`
   is on. Any exception would show a stack trace and environment details to
   the visitor. Set `APP_DEBUG=false` and run `php artisan config:cache`.
2. **Production loads its analytics up to three times.** Google Tag Manager
   (`GTM-NC5WTJ68`) and the Meta Pixel (`2062877844599564`) each load twice: once
   from their Settings fields and once from Google's and Meta's own snippets
   pasted into *SEO & Tracking → Advanced → Custom head scripts*. GA4
   (`G-864KLDCN57`) is loaded by the GA4 field and again by a Google tag inside
   the GTM container. On the article page this is 554 KB of Google tag script
   and 1.7 s of main-thread blocking on a mid-range phone. Each GA4 page view
   was also sent twice.

Main bottlenecks measured (Lighthouse, mobile):

| Bottleneck | Where | Evidence |
| --- | --- | --- |
| Third-party scripts (GTM, gtag, Meta) | every page | 1.0–2.2 s blocking time, 0.8–1.4 MB of script |
| Large original images | homepage, loan pages, every page (logo) | LCP banner a 615 KB JPEG, next slides 970 KB + 517 KB fetched before LCP, loan image 774 KB, 3000 px logo 111 KB shown at 36 px |
| `livewire.js` on pages without Livewire | homepage, contact, articles, about… | loaded only for the Alpine inside it |
| Slow server response on loan pages | loan pages | 0.9 s TTFB (content cache not yet deployed) |
| Analytics blocked by the CSP | every page | GA4 hits to `analytics.google.com`, `www.google.com`, `stats.g.doubleclick.net` refused |
| HTTP/1.1 only | origin | server offers no `h2` in TLS ALPN |

Highest-impact fixes made in the code:

- `livewire.js` now loads only on pages that render a Livewire component; the
  rest start a standalone Alpine (54 KB instead of 254 KB minified).
- Carousel slides after the first no longer download with the page.
- WebP copies (`srcset`) for loan product images, article covers and the logo,
  as banners already had, plus image dimensions and lazy loading below the fold.
- GA4's blocked endpoints added to the CSP, narrowly.
- The admin now warns when a tag's own snippet duplicates a Settings field.
- Accessibility: faint-text contrast to WCAG AA, 24 px carousel dot targets,
  descriptive banner link text. Desktop layout shift from the scrollbar fixed.

**Remaining risks:** the duplicate tags and `APP_DEBUG` are fixed only by the
production steps in section F; until then the live scores barely move. The
new code has not been measured on production yet (section B).

## B. Performance comparison

### Production baseline (before), 31 October 2026

Lighthouse 12.6.1, headless Chromium on this machine, default simulated
throttling (mobile: Moto G Power, slow 4G, 4× CPU; desktop preset). Median of
3 runs per page and form factor, run one after another (42 page loads in about
40 minutes). These are **lab** figures. **No field data (CrUX/INP) was
available:** the PageSpeed Insights API answered `429 Queries per day` for the
anonymous quota, and no API key is configured.

| Page | Mobile perf | Mobile LCP | Mobile TBT | Desktop perf | Desktop LCP | Desktop CLS | TTFB |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| `/` | 55 | 7.1 s | 895 ms | 80 | 3.2 s | 0.007 | 357 ms |
| `/loans/personal-loans` | 53 | 7.4 s | 964 ms | 92 | 1.4 s | 0.000 | 913 ms |
| `/loans/home-loan` | 50 | 8.2 s | 1,121 ms | 88 | 1.5 s | 0.001 | 928 ms |
| `/loans/business-loan` | 48 | 7.1 s | 1,467 ms | 92 | 1.3 s | 0.091 | 925 ms |
| `/resources/how-emis-work` | 67 | 2.9 s | 2,284 ms | 96 | 0.7 s | 0.000 | 290 ms |
| `/calculators/emi/personal-loan` | 71 | 1.8 s | 1,313 ms | 90 | 0.7 s | 0.006 | 401 ms |
| `/contact` | 65 | 3.0 s | 2,337 ms | 81 | 0.7 s | 0.005 | 287 ms |

Every page scored 94–97 accessibility, 74–75 best practices, and 92 (home) or
100 SEO. CLS on mobile was 0 everywhere. Scores varied between runs (the
homepage desktop ranged 42–82), which is why medians are reported.

What the numbers mean: the **observed** LCP (unthrottled) was 0.5–1.2 s on every
page. The 7–8 s mobile figures are Lighthouse's projection to a slow phone, and
it counts every byte requested before the LCP: the full-size images and the
~550 KB of Google tag script that the `<head>` starts loading. So image bytes,
third-party scripts and TTFB are the levers, in that order.

Other baseline facts:

- **Transfer:** homepage 3.0 MB on mobile (2.25 MB images, 0.7 MB script, of
  which 0.57 MB third-party), 6.5 MB on desktop. HTML 47–57 KB compressed
  (550–860 KB raw). CSS 22 KB compressed. 15–37 requests.
- **DOM:** 1,987 elements on the homepage, 3,413–4,981 on loan pages. About
  half of the homepage HTML is the header's desktop Loans menu (~450 links),
  kept in the HTML on purpose for crawlers (`docs/redis-cache.md` §8).
- **Console errors:** CSP refusals of GA4 hits and of Meta's Conversions API
  gateway hosts on every page.
- **Caching:** build assets `max-age=31536000, immutable`; uploads 30 days
  with ETags; HTML `no-cache, private`. Correct.
- **Compression:** gzip on HTML, CSS and JS. No Brotli.
- **Protocol:** HTTP/1.1 for every first-party request.
- **Images:** JPEG/PNG originals only; the responsive WebP copies return
  **403** (they were never generated on production, and Laravel's signed
  `storage/{path}` route answers missing `/storage` files with 403).

### Local before/after (identical data)

Production could not be re-measured, because nothing has been deployed. Instead
the same pages were measured on this machine against the same database and
uploads, once with the code as it was (a copy of the working tree before these
changes, on :8092) and once with the changes (:8091), runs interleaved.

LOCAL_RESULTS_PLACEHOLDER

## C. Files changed

| File | Change |
| --- | --- |
| `resources/views/components/layouts/app.blade.php` | `@livewireStyles` / `@livewireScripts` removed; Livewire injects them itself, only on pages with a component |
| `resources/js/app.js` | Starts a standalone Alpine (dynamic import) when `window.Livewire` is absent |
| `resources/views/components/site/banner-carousel.blade.php` | Slides after the first are `hidden` until `preloadNeighbours()` (window load); 24 px dot buttons; screen-reader context on the CTA |
| `app/Models/Concerns/HasResponsiveImage.php` (new) | Banner's WebP-copy lifecycle as a trait; soft delete keeps copies, force delete removes them |
| `app/Models/Banner.php`, `LoanProduct.php`, `Article.php` | Use the trait |
| `app/Support/Media/ResponsiveImage.php` | Per-call widths (`LOGO_WIDTHS` = 64, 128), `dimensions()`, SVG skipped |
| `app/Console/Commands/GenerateResponsiveImages.php` | Also backfills loan products, articles and the logo |
| `app/Filament/Pages/Settings.php` | Makes/removes the logo's copies on save |
| `app/Providers/AppServiceProvider.php` | `siteBranding['logoSrcset']` |
| `resources/views/components/site/header.blade.php`, `footer.blade.php` | Logo served from its WebP copies |
| `resources/views/loans/show.blade.php` | Loan image: `<picture>` + WebP, width/height, `loading="lazy"` |
| `resources/views/resources/show.blade.php`, `components/site/article-card.blade.php` | Article cover and cards: `<picture>` + WebP, width/height on the cover |
| `app/Support/Analytics/TrackingScripts.php` | GA4 hosts in `connect-src`; `duplicates()` |
| `app/Filament/Pages/SeoAnalytics.php` | Warning callout for duplicated tags; GA4 helper text |
| `resources/css/app.css` | `ink-faint` at AA contrast; `scrollbar-gutter: stable`; no `@source` for compiled views |
| `tests/…` | See section D |
| `.ai/rules/*` | The decisions above, recorded for the next change |

Not changed, on purpose: the header mega menu stays in the HTML (an SEO
decision already taken); fonts were not added (see section F); full-page
caching was not added (`docs/redis-cache.md` §4); no dependency changed.

## D. Tests

TESTS_PLACEHOLDER

## E. Production status

**Local changes tested; deployment awaiting approval.**

## F. Remaining work, needing access or a decision

### F.1 Do now on the server, no code needed

These two are independent of the deploy and carry most of the live gain.

1. **Turn debug off.** In production `.env`: `APP_ENV=production`,
   `APP_DEBUG=false`. Then `php artisan config:cache`. Check: the
   `livewire.js` linked from `/calculators/emi/personal-loan` should be about
   254 KB, not 590 KB.
2. **Remove the duplicate tags** (Admin → SEO & Tracking). Copy the current
   text of every Advanced field somewhere safe first, so it can be restored.
   - *Advanced → Custom head scripts:* delete Google's GTM snippet
     (`GTM-NC5WTJ68`) and Meta's pixel snippet (`fbq('init', '2062877844599564')`,
     with its `<noscript>`). Keep the Ahrefs line, which has no field of its own.
   - *Advanced → Body start scripts:* delete any GTM or pixel `<noscript>`
     there; the fields render their own.
   - *Analytics & Tracking:* pick **one** source for GA4. The GTM container
     already contains a Google tag for `G-864KLDCN57`, so the simplest choice is
     to switch **Google Analytics enabled** off and let GTM send it. Confirm in
     GTM that this tag fires on all pages (Initialization or All Pages) before
     switching. The other choice is to remove that Google tag from GTM and keep
     the field.
   - After the deploy below, the page shows a warning while any duplicate is
     left.

### F.2 Deploy the code (awaiting your approval)

Nothing below has been run. The deploy method on the server is not recorded
anywhere in the repository; `docs/redis-cache.md` implies `git pull` on the
server, and `public/build` is not in git, so assets are built there or
uploaded. Please confirm which.

**What would ship.** The `production` branch is at `5b95c7f`. On top of it:

| Change | State | Migration |
| --- | --- | --- |
| Content cache and cached mega menu (`78c785e`, `063631e`) | committed, not deployed | `2026_10_30_091234_create_content_cache_versions_table` (new small table) |
| 404/410 handling (redirect Gone rules, 404 log) | **uncommitted**, separate work in progress | `2026_10_31_084214`, `…084215`, `…084217` (one column change, one data insert, one new table) |
| This performance work | **uncommitted** | none |

**Decision needed:** ship the performance work with the 404 work, or without
it. Without it, the performance changes have to be committed on their own;
`AppServiceProvider.php` and `.ai/rules/index.md` hold lines from both pieces
of work, and those split cleanly (checked). With it, the 404 report says that
work is finished, but it has its own migrations and its own rollback caveat
(section G).

**Steps, once approved:**

```bash
# 0. Record what is live and back up the database
cd <deploy directory>
git rev-parse HEAD > ~/fynnedge-rollback-commit.txt
mysqldump --single-transaction --routines fynnedge | gzip > ~/fynnedge-2026-10-31.sql.gz
cp .env ~/fynnedge-env-2026-10-31.backup

# 1. Code
git fetch origin && git checkout <approved commit>
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build            # or upload the public/build built locally

# 2. Database: only the migrations listed above, all additive
php artisan migrate --force

# 3. Caches and PHP
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl reload php-fpm      # or httpd under mod_php: clears OPcache
php artisan content-cache:warm

# 4. WebP copies of existing uploads (banners, loan products, articles,
#    founder photo, logo). Needs GD with WebP; run as the web server user so
#    the files stay writable by the admin panel.
php -r 'var_dump(function_exists("imagewebp"));'
sudo -u apache php artisan media:responsive-images   # user may be nginx/www-data/your account
```

**Checks after the deploy** (a handful of requests, not load):

- `/`, `/loans/personal-loans`, `/loans/home-loan`, `/loans/business-loan`,
  `/resources/how-emis-work`, `/calculators/emi/personal-loan`, `/contact`
  answer 200; `/sitemap.xml` and `/robots.txt` answer 200; `/credit-card`
  answers one 301 to `/loans/credit-card`; a made-up URL answers 404.
- The homepage HTML has no `livewire.js`; the calculator page has it once.
- The banner `<source type="image/webp">` URLs answer 200 (not 403).
- Browser console on `/` and a calculator: no CSP errors for
  `analytics.google.com`, `www.google.com` or `stats.g.doubleclick.net`; the
  header menus, banner carousel, contact form and calculator work.
- GA4 Realtime shows one page view per visit, not two.
- `storage/logs/laravel.log` has no new exceptions.
- Then re-run Lighthouse on the same seven pages (3 runs each) and compare
  with section B.

### F.3 Needs the hosting provider or server administrator

- **HTTP/2.** The server offers only `http/1.1` in TLS ALPN, so the browser
  cannot use HTTP/2, and `.htaccess` cannot change that. It needs `mod_http2`
  loaded and `Protocols h2 http/1.1` in the HTTPS virtual host. `mod_http2`
  does not work with the `prefork` MPM, so if PHP runs as `mod_php` this also
  means switching to the `event` MPM with PHP-FPM. Verify with
  `curl -sI --http2 https://fynnedge.com/ | head -1` → `HTTP/2 200`. On a
  managed plan, ask the provider to enable it.
- **Brotli** (optional): `mod_brotli`, for the same text types `mod_deflate`
  already compresses. Smaller HTML and CSS than gzip.
- **Redis** for the cache (optional): fully documented in
  `docs/redis-cache.md` §6. Not required for these changes.
- **Hide the PHP version** (minor): the `X-Powered-By: PHP/8.5.12` header
  comes from `expose_php = On`.

### F.4 Decisions for the business

1. **GA4 source of truth**: GTM's Google tag or the GA4 field (F.1).
2. **When the tags load.** After the duplicates go, GTM and the Meta Pixel are
   still the largest main-thread cost on every page (on the article page in
   the baseline, 1.7 s of blocking from Google's tags and 0.4 s from Meta's). Starting
   them after the page has loaded, or on the first scroll or tap, is the next
   largest gain. It would miss visitors who leave before that point, so it is
   an analytics decision, not a code one.
3. **Meta Conversions API gateway.** The pixel also tries to reach
   `demo-1.conversionsapigateway.com` and an `*.ecs.us-east-1.on.aws` host,
   which the CSP refuses. If those are the business's own gateway, their exact
   hosts can be added to the CSP. If not, the gateway can be switched off in
   Meta Events Manager. They were deliberately not allowed blind.
4. **Fonts.** Fraunces and IBM Plex are built by Vite, but no page has ever
   loaded them (the layout never called `Vite::fonts()`), so the site shows
   in system fonts. Leaving it that way is the fastest option. Loading them
   would change how the site looks and add 60–90 KB.
5. **Best Practices score.** It stays below 95 while the Meta Pixel and Google
   tags set third-party cookies and the gateway errors remain. That is the
   cost of those tags, not a defect in the site.

### Targets: what is and is not reachable

The mobile 85+ target is not reachable while the third-party tags load on
every page in the `<head>`: in the baseline they alone block the main thread
for 1.0–2.2 s on mobile. The code changes and the F.1 cleanup take out the
duplicated half of that. Accessibility and SEO should reach 95–100. Best
Practices is limited as described in F.4.

## G. Rollback

| What | How to undo | Data effect |
| --- | --- | --- |
| The whole deploy | `git checkout $(cat ~/fynnedge-rollback-commit.txt)`, `composer install --no-dev --optimize-autoloader`, rebuild assets, `config:cache`/`route:cache`/`view:cache`, reload PHP-FPM | none from this work |
| Only the Livewire/Alpine change, if a page's menus or forms stop responding | Put `@livewireStyles` back before `</head>` and `@livewireScripts` before `{!! $trackingBodyEnd !!}` in `components/layouts/app.blade.php`, delete the last block of `resources/js/app.js`, rebuild | none |
| WebP copies | Nothing to do: a page serves the original upload whenever a copy is missing. To remove them anyway, delete `storage/app/public/responsive/{loan-products,articles,branding}` | files only |
| `APP_DEBUG=false` | Not to be undone | none |
| Admin tracking cleanup | Paste back the saved copy of the Advanced fields; switch the GA4 field back on | settings only |
| Content cache table (`78c785e`) | Can stay; or `php artisan migrate:rollback --step=…` for that one migration | drops an empty helper table |
| 404 work migration `2026_10_31_084214` | **Rolling it back deletes every Gone (410) redirect row** (its `down()` must, to restore `destination NOT NULL`). Restore from the backup instead if needed | deletes rows |
| HTTP/2 | Remove the `Protocols` line, reload Apache | none |

This performance work adds no migration, so the database never needs
rolling back for it.
