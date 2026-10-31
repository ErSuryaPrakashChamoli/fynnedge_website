# Redis and the public content cache

How FynnEdge caches public website data, how an edit in the admin panel refreshes it, and how to run Redis in production.

> **The one rule.** An edit, whether a rate, a landing page, a setting or a redirect, must appear on the next page view without anyone clearing a cache. Caching never decides what is published. The database does, and every cached copy is keyed to the database's own record of what changed.

---

## 1. What was slow (measured before any change)

Measured on 2026-10-30 against a copy of the production data (MySQL 8.4, PHP 8.5, OPcache on), one request at a time, with the app freshly booted for each request:

| Bottleneck | Cost | Fix |
| --- | --- | --- |
| `x-site.nav-link` cast the route-bound `LoanProduct` (with its lenders, FAQs and landing pages loaded) to a string, i.e. 350 KB of JSON, for every menu link on a loan page | ~28 ms per link, **~300 ms per loan and landing page** | The comparison now skips models. The output is unchanged, see §7 |
| `RateBook::quotesFor()` rebuilt all 617 rate quotes, then filtered them, once per lender row of the comparison table | ~6.6 ms per row, **~260 ms on a 39-lender page** (now 1.6 ms for all 39) | `live()` is memoised per second and language, and grouped by offer once |
| `LoanMegaMenu::categories()` rebuilt the header's menu of ~400 landing pages | **~65 ms on every page** (only ~4 ms of it SQL) | Cached per language |
| `Setting::get()` made one cache lookup per key, and a page reads ~90 keys. With `CACHE_STORE=database` each lookup is a SQL query | **~93 extra queries on every page** | One cached map of all settings |
| `LoanCalculatorPreset` queried `loan_products` once per loan category | ~14 queries on every loan page and calculator | One cached query |
| Header and footer each ran the same `loan_products` query | 2 queries on every page | One cached query |

What was **not** a bottleneck: the remaining single-row queries (≈0.5 ms each), the frontend assets (`app.css` is 20 KB gzipped, `app.js` 7 KB, both already cached for a year by `.htaccess`), and the transferred HTML (a 915 KB loan page compresses to 57 KB with the existing `mod_deflate` rules).

Results after the change are in §9.

---

## 2. How the cache works

Everything goes through `App\Support\ContentCache\ContentCache`:

```php
ContentCache::remember('loan-mega-menu:hi', [ContentCacheSection::LoanCatalog], fn () => ..., scheduled: [LoanProduct::class, LoanLandingPage::class]);
ContentCache::invalidate(ContentCacheSection::Rates);   // after commit
ContentCache::invalidateTable('loan_landing_pages');    // for writes that skip model events
```

- **Sections.** `App\Enums\ContentCacheSection` groups the data that changes together: `settings`, `loan-catalog`, `rates`, `redirects`, `page-seo`. Each section has a **version number in the `content_cache_versions` table**.
- **Versioned keys, never deletes.** A cached entry's key holds the versions of the sections it was built from, e.g. `content:loan-mega-menu:hi:loan-catalog.42`. An edit bumps the version, the next reader looks under a new key, and the old entry simply expires. Nothing scans or deletes keys, and no Redis-specific behaviour is assumed.
- **Versions live in MySQL, not Redis**, so an eviction, restart, flush or outage of Redis can never lose an invalidation. The worst it can do is make the cache cold. Reading all versions is one indexed query per request.
- **After commit only.** `invalidate()` bumps the version through `DB::afterCommit()`. A rolled-back edit bumps nothing, and while a transaction that changed content is still open, reads in that request go straight to the database and are not cached. The version is read *before* a value is built, so a value built while an edit was committing is stored under the old version and never trusted. This was the race RateBook's first design lost.
- **Scheduled content.** `scheduled:` lists the `Publishable` models a value filters by `published_at` / `expires_at`. The entry then also expires at the next such moment, so scheduled content goes live, and lapses, on time without an edit. Rates need no schedule: `RateBook` applies effective dates and promotion windows when the rates are read.
- **TTL is a safety net**, not the refresh mechanism: `CONTENT_CACHE_TTL` (default 3600 s). It bounds how long a copy could outlive a change made behind the application's back, such as raw SQL on the server.
- **Stampede.** A miss is rebuilt under a short lock (`content-lock:{name}`, 10 s, waiting ≤ 2 s), so a burst of requests after an edit runs the queries once. If the lock can't be had, the value is built anyway and the page never waits on it.
- **Outage.** If the store fails, the value is built from the database and the store is skipped for the rest of that request (one warning in the log per request). A deploy that runs before its migration (no versions table yet) serves content uncached instead of failing.
- **Only arrays and scalars.** `cache.serializable_classes` is `false`, so a cached object would come back as `__PHP_Incomplete_Class`. `remember()` throws for objects in every environment, tests included. Model rows are cached as arrays and hydrated (`LoanProduct::hydrate()`, `newFromBuilder()`).

---

## 3. What is cached

| Entry | Built from | Section(s) | Also expires | Read by |
| --- | --- | --- | --- | --- |
| `settings` (every row, ~2 KB) | `settings` | settings | TTL | `Setting::get()`: branding, contact, theme, tracking, SEO defaults |
| `loan-mega-menu:{en,hi}` | `loan_products`, `loan_landing_pages` | loan-catalog | next product or landing page publish/expiry | header mega menu (data), mobile menu |
| `loan-mega-menu-panel:{en,hi}:{site address}` (rendered HTML, ~190 KB) | the entry above | loan-catalog | same as above | header desktop Loans panel (`components/site/partials/loan-mega-menu-panel.blade.php`) |
| `navigation-loan-products` | `loan_products` | loan-catalog | next product publish/expiry | footer "Loans" column |
| `calculator-products` (~75 KB) | `loan_products` | loan-catalog | next product publish/expiry | `LoanCalculatorPreset` (calculators, loan pages) |
| `rate-book:published-rates:v4` (~617 rows) | `lender_product_rates`, `lender_products`, `lenders`, `loan_products` | rates | TTL; date rules applied at read | `RateBook`: every rate on the site |
| `redirects:active` | `redirects` | redirects | TTL | `HandleRedirects` (every request) |
| `page-seo:active` (path → id) | `page_seos` | page-seo | TTL | the layout's per-URL SEO overrides |

### Dependency map (`ContentCacheSection::forTable()`)

| Table changed | Sections invalidated |
| --- | --- |
| `settings` | settings |
| `loan_products` | loan-catalog, rates (slug and category are in RateBook's rows) |
| `loan_landing_pages` | loan-catalog |
| `lender_product_rates`, `lender_products`, `lenders` | rates |
| `redirects` | redirects |
| `page_seos` | page-seo |
| anything else | nothing is cached from it; it is read on every request |

### Every write path that reaches these tables

| Path | How it invalidates |
| --- | --- |
| Admin create / edit / delete / bulk delete / restore / force-delete / replicate / toggle columns / importers / restore from History | Model events, through `InvalidatesContentCache` (Setting, LoanProduct, LoanLandingPage, Redirect, PageSeo) and the existing `RateBook::flush()` hooks (LenderProductRate, LenderProduct, Lender, LoanProduct) |
| `RateWorkflow` publish, verify, bulk publish (inside transactions) | Model events; the bump waits for the commit |
| Settings pages (`Setting::set()`) | Model events |
| Hindi auto-translation job (`saveQuietly()`, skips events) | `ContentCache::invalidateTable()` in `TranslateContentToHindi` |
| Hindi backfill `ContentTranslations::apply()` (`DB::table()`, skips events) | `ContentCache::invalidateTable()` per table written |
| Hit counter on a redirect (query builder) | Deliberately none: hits don't change the map |
| A product or lender force-deleted (database cascades to offers, rates, landing pages) | The parent's own `deleted` event invalidates every section the children feed |
| Filament drag-reorder (`->reorderable()`, skips events) | None of the cached tables is reorderable. Making one reorderable needs an `afterReordering` call to `invalidateTable()` |
| Raw SQL on the server | Not covered. Run `php artisan content-cache:refresh {section}`, or wait for the TTL |

Pinned by `tests/Feature/ContentCache/*`.

---

## 4. What is deliberately not cached

- **Full HTML pages.** Every public page carries the visitor's own CSRF token (forms, `data-csrf` for Livewire), sets session and `XSRF-TOKEN` cookies, and its header differs for a signed-in customer ("My Profile") and by language. A shared page cache would hand one visitor's token and header to the next. Responses keep Laravel's `Cache-Control: no-cache, private`, so no proxy or browser stores them. **No route is page-cached.** The data caching above recovered most of the time a page cache would have saved, without that risk.
  The one rendered fragment that *is* cached, the header's desktop Loans panel, contains nothing that varies by visitor: no active link, no token, nothing signed-in. It is keyed by language and by site address, because its links are absolute URLs. Keep it that way (`.ai/rules/support-loans.md`).
- **Anything personal or transactional:** customer portal, journeys, applications, OTPs, credit-score checks, bureau responses, form submissions. None of it touches `ContentCache`. The cache only ever holds what is built from the content tables in §3.
- **Per-page placement lists** (promo bar, FAQs, social and video testimonials, footer pages and links). Each is one ~0.5 ms indexed query on a small table, which costs about the same as a cache round trip.
- **The sitemap.** It takes ~100 ms to build, but only crawlers fetch it, a few times a day. Because it is uncached it is always exactly current, so a URL change appears in it immediately.
- **The EMI calculator's amortization schedule.** It is computed from the visitor's own inputs.

There is no CDN or reverse proxy in front of the site (Apache serves the apex directly), so there is nothing further to purge after an edit.

---

## 5. Operating it

| Command | What it does |
| --- | --- |
| `php artisan content-cache:status` | Store health (a write/read probe), every section's version and when it last changed, and on Redis: memory used/limit, eviction policy, hit rate, evicted keys, clients. Exits non-zero if the store is failing. Never prints a cached value or a credential. |
| `php artisan content-cache:refresh rates` (or `--all`) | Bumps the named sections. For changes made with raw SQL only. Granular, logged with the OS user, and never a flush. |
| `php artisan content-cache:warm` | Builds settings, rates, redirects, SEO overrides and the mega menu (data and rendered panel) in both languages, in-process. The panel is built for `APP_URL`, so `APP_URL` must be the address visitors use (`https://fynnedge.com`). No HTTP request, so no session, form, OTP, bureau call or database write is triggered. Run it after a deploy or a Redis restart. |
| `php artisan rates:status --refresh` | As before; now bumps the `rates` section. |

**Do not run `php artisan cache:clear`** to "refresh the site": edits already refresh themselves, and a flush would also drop locks and rate-limit counters. With the Redis ACL in §6 it is refused anyway.

**Admins never need to clear anything.** If an edit "isn't showing":

1. Run `content-cache:status`. The section's *Last invalidated* time should match the edit.
2. If the time doesn't match, the write bypassed the model (raw SQL). Run `content-cache:refresh <section>`.
3. If it does match, the text is probably hardcoded in a template rather than editable (see `.ai/rules/views-http-controllers.md`).

---

## 6. Production deployment

The production server (AlmaLinux, Apache 2.4) was not accessible from this work, so nothing below has been run there. Each step is for the server administrator.

### Stage 1: deploy the code, no Redis yet (safe on its own)

The cache works on the current `CACHE_STORE=database`, so this stage already removes the ~90 settings queries per page and the render bottlenecks.

```bash
git pull                                  # this branch
composer install --no-dev --optimize-autoloader   # no new packages
php artisan migrate --force               # creates content_cache_versions (tiny, no locks on existing tables)
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo systemctl reload php-fpm             # or httpd if PHP runs as mod_php: clears OPcache
php artisan content-cache:warm
php artisan content-cache:status
```

Verify: edit a landing page title in the admin panel and reload the home page; the mega menu shows it. Change a rate and reload its loan page.

### Stage 2: Redis for the cache

**Install.** Check the versions first, and use whichever package matches the server:

```bash
cat /etc/almalinux-release; php -v; rpm -qa | grep -E '^php|redis|valkey'
# AlmaLinux 9:  sudo dnf install redis          (or: sudo dnf module install redis:7)
# AlmaLinux 10: sudo dnf install valkey          (Redis-compatible; service name valkey)
# PHP extension (phpredis; config already uses REDIS_CLIENT=phpredis):
#   distro or Remi module PHP: sudo dnf install php-pecl-redis6
#   Remi SCL PHP 8.5:          sudo dnf install php85-php-pecl-redis6
php -m | grep -i redis                    # must print "redis"
```

**Configure** `/etc/redis/redis.conf` (or `/etc/valkey/valkey.conf`). It is a private, cache-only instance:

```conf
bind 127.0.0.1 -::1          # never a public interface
protected-mode yes
port 6379
maxmemory 128mb              # the data set is a few MB; 128-256mb is ample
maxmemory-policy allkeys-lru # it is a cache: evict, never refuse writes
save ""                      # no snapshots: the database is the source of truth
appendonly no
# The app's own user. Destructive and admin commands are refused; INFO is allowed for content-cache:status.
user default off
user fynnedge on >REPLACE_WITH_A_LONG_RANDOM_PASSWORD ~* &* +@all -@dangerous +info
```

```bash
sudo systemctl enable --now redis        # or valkey; restarts on failure by default
redis-cli --user fynnedge --pass '…' ping   # PONG
sudo firewall-cmd --list-all              # 6379 must NOT be listed
ss -ltnp | grep 6379                      # must show 127.0.0.1 only
```

**`.env`** (the password goes only in `.env`, never in the repository):

```dotenv
CACHE_STORE=failover
CACHE_FAILOVER_STORES=redis,database   # Redis first; the cache table while Redis is down
CACHE_PREFIX=fynnedge:website:
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_USERNAME=fynnedge
REDIS_PASSWORD=REPLACE_WITH_THE_SAME_PASSWORD
REDIS_TIMEOUT=0.5
REDIS_READ_TIMEOUT=1
REDIS_MAX_RETRIES=1
# Unchanged on purpose:
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

```bash
php artisan config:cache
sudo systemctl reload php-fpm
php artisan content-cache:warm
php artisan content-cache:status          # Store read/write OK, Redis section shows memory and hit rate
```

**Why sessions and queues stay on the database.** On an `allkeys-lru`, unpersisted instance, Redis sessions would make a Redis outage or eviction sign every customer and admin out and fail every page. Queued jobs (paid bureau calls, newsletters) could be evicted or lost on restart. The database already handles both at this traffic level. Moving them needs a separate Redis with `noeviction` and persistence, plus its own migration and rollback plan.

**Failure behaviour.** With `failover`, if Redis stops, locks, rate limits and cached content carry on in the database cache table. The short timeouts stop a dead Redis from hanging a page. Because versions are in MySQL, content is correct the moment Redis returns, with no flush needed.

### Monitoring

- `php artisan content-cache:status`: hit rate should climb above ~95% once warm, evicted keys should stay near 0, and memory should stay well under the limit.
- The log line `Content cache store unavailable` means Redis is unreachable and content is being served from the database.
- `redis-cli … INFO stats` and `INFO memory` for the raw figures.

---

## 7. Rollback

| Situation | Action | Effect |
| --- | --- | --- |
| Cached content ever suspected wrong | `CONTENT_CACHE_ENABLED=false`, then `php artisan config:cache` | Every page reads the database as before; nothing is cached. No deploy needed. |
| Redis misbehaving | `CACHE_STORE=database`, then `php artisan config:cache` | Back to the cache table. Content stays correct (versions are in MySQL). |
| Undo the code | Revert the commit and deploy | The `content_cache_versions` table can stay, or be dropped with `migrate:rollback --step=1`. |

Behaviour preserved on purpose: the header and footer markup is unchanged. One latent bug is kept as it was. On a loan page, `nav-link` has never marked the current product as the current page, because the old code compared the product's JSON with its slug. Highlighting it would be a visible change, so it is left for a separate decision (`.ai/rules/site.md`).

---

## 8. Remaining slow spots

- **Loan page lender comparison table:** ~100 ms of Blade markup for 39 lenders, since the rate lookups are now ~2 ms.
- **EMI calculator (Livewire):** ~130 ms rendering the month-by-month schedule from the visitor's inputs.
- **Header markup:** the desktop Loans panel is still ~190 KB of HTML and ~450 links on every page, though only ~7 KB gzipped. Its render is now cached. Shrinking it further means loading the panel on first hover, which would take those internal links out of the HTML crawlers read first. That is an SEO decision, so it is not done.

---

## 9. Results

Measured on 2026-10-30. Each "before" request was handled by a checkout of the previous commit and each "after" request by this change, interleaved on the same machine against the same local MySQL copy, with PHP 8.5 and OPcache on. Every request booted a fresh app, as PHP-FPM does. Each figure is the **median of 9 requests**. Both sides used `CACHE_STORE=database`, because Redis is not installed on the development machine. "After, cold" is the first request after every section was invalidated, which is what the first visitor after an edit pays; `content-cache:warm` removes it after a deploy.

| Page | Before | After (warm) | After (cold) | Queries before → after | Cache-store calls before → after |
| --- | ---: | ---: | ---: | ---: | ---: |
| `/` | 383 ms | **222 ms** | 322 ms | 26 → 23 | 101 → 5 |
| `/loans` | 245 ms | **144 ms** | 293 ms | 11 → 8 | 92 → 5 |
| `/loans/personal-loan` | 1,219 ms | **284 ms** | 492 ms | 33 → 15 | 95 → 7 |
| `/loans/personal-loan/1-lakh-personal-loan` | 1,193 ms | **397 ms** | 770 ms | 33 → 15 | 95 → 7 |
| `/calculators/emi/personal-loan` | 511 ms | **178 ms** | 329 ms | 30 → 11 | 97 → 7 |
| `/about` | 270 ms | **121 ms** | 245 ms | 13 → 10 | 95 → 5 |
| `/resources/how-emis-work` | 284 ms | **135 ms** | 397 ms | 12 → 9 | 93 → 5 |
| `/credit-score/cibil` | 360 ms | **141 ms** | 243 ms | 11 → 8 | 104 → 11 |
| `/partners` | 269 ms | **125 ms** | 272 ms | 11 → 8 | 93 → 5 |

"Queries" are application queries, excluding the cache table and sessions; each page also makes 3 session queries, unchanged. "After" includes the one new query that reads the section versions.

How to read it:

- The development machine was heavily loaded during the run (load average 5–28 on 8 cores, swap full), so absolute times are inflated. The before/after ratio is the meaningful figure. Production times will differ, and should be re-measured there after Stage 1 (`curl -w '%{time_starttransfer}'` on these URLs, ten times each).
- With `CACHE_STORE=database`, the remaining 5–11 cache-store calls are SQL queries. With Redis they become sub-millisecond Redis reads. That is Stage 2's gain on top of these figures, not yet measured because Redis is not installed here.
- A cold request rebuilds and stores everything at once, so on a few pages it costs about as much as a warm request did before. It happens once per edit per language.
- Correctness after an edit is covered by the automated tests (`tests/Feature/ContentCache/*` and the translation tests), not by these timings.

### Follow-up: caching the rendered desktop mega menu panel

Measured the same way against the previous commit (`78c785e`, which already had everything above), on a quieter machine (load average ~3.5). Each figure is the median of 12 requests:

| Page | Before | After (warm) | After (cold) | HTML before → after |
| --- | ---: | ---: | ---: | ---: |
| `/` | 133 ms | **111 ms** | 249 ms | 552 → 518 KB |
| `/about` | 89 ms | **79 ms** | 202 ms | 378 → 343 KB |
| `/loans` | 93 ms | **76 ms** | 202 ms | 376 → 340 KB |
| `/loans/personal-loan` | 205 ms | **191 ms** | 402 ms | 893 → 858 KB |
| `/calculators/emi/personal-loan` | 137 ms | **129 ms** | 274 ms | 485 → 449 KB |
| `/resources/how-emis-work` | 101 ms | **94 ms** | 246 ms | 366 → 330 KB |

Query counts are unchanged. The ~35 KB HTML saving comes from the extracted partial carrying 24 fewer spaces of indentation per line; the markup is otherwise identical. The quieter machine is why these absolute times are lower than the first table's.
