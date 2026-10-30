# URL normalisation, redirects and crawl control

How fynnedge.com decides which URL a page lives at, where each rule is
implemented, and what to check before and after a deploy. Written after the
October 2026 Search Console "Page with redirect" report. The full per-URL
mapping for that report is in
[gsc-page-with-redirect-audit-2026-10.csv](gsc-page-with-redirect-audit-2026-10.csv).

The canonical form of every URL is `https://fynnedge.com/<path>`:
HTTPS, no `www`, no trailing slash (except `/`), no doubled slashes.

## Where each rule lives

A request passes through these layers in order. Each rule lives in exactly
one layer, so no two layers rewrite the same thing.

| Layer | Rule | Where |
| --- | --- | --- |
| Cloudflare (www only) | `www.fynnedge.com/*` → 301 `https://fynnedge.com/*` | Cloudflare dashboard → Rules → Redirect Rules (**see "Production changes"**) |
| Apache vhost (apex) | `http://fynnedge.com/*` → 301 `https://fynnedge.com/*` | Server vhost config (already correct, not in git) |
| `public/.htaccess` | Serve existing files; everything else → `index.php` | Repo. Must **not** strip trailing slashes (see below) |
| `HandleRedirects` (global middleware) | Admin redirect rows, then slash normalisation | `app/Http/Middleware/HandleRedirects.php` |
| Routes | Pages; `/language/{locale}` action | `routes/web.php` |

### Redirect rows (Admin → Redirects)

- Use them for any old URL that has a **genuinely equivalent** page. Use 301
  for a permanent move and 302 only for something temporary.
- Do **not** point an old URL at the homepage or a listing page just to make
  a report go quiet. Google treats that as a soft 404. If nothing equivalent
  exists, let the URL 404.
- Matching ignores case, trailing slash and query string, and the visitor's
  query string is carried across.
- Chains are flattened at request time: if `/a → /b` and later `/b → /c`, a
  request for `/a` gets one 301 straight to `/c` (`Redirect::resolve()`). The
  hop is 302 if any row in the chain is temporary. A loop serves the page
  instead of bouncing. Still tidy chains up when you see them, so the
  table stays readable.
- Hit counters show which old URLs still get traffic. `seo:audit`'s own
  requests are not counted.
- Rows can also be shipped in a migration (production only runs `migrate`).
  Such a migration must skip any source that already has a row, because an
  admin's row always wins. See
  `database/migrations/2026_10_30_072910_add_legacy_wordpress_redirects.php`.

### Trailing and doubled slashes

`public/.htaccess` used to strip trailing slashes before Laravel saw the
request. That produced two Search Console problems:

- `/old-page/` → `/old-page` → its redirect row: **two hops**.
- `/deleted-page/` → `/deleted-page` → 404: **a redirect to a 404**.

`HandleRedirects` now does it instead. It matches the redirect row first,
whatever the slashes. It sends a slash variant to its clean path **only when
that path is a real route**, so any other URL gets its 404 straight away. Do
not re-add the stock Laravel "Redirect Trailing Slashes" rule to `.htaccess`.

### The language switch is an action, not a page

English/Hindi is a cookie (`site_locale`) on the same URL. The header switch
posts a form to `/language/{en|hi}` with the return path as a field. It must
never be rendered as an `<a href>`: that put two crawlable
`/language/…?return=…` URLs on every page, and Google crawled them all
(`rel="nofollow"` is a hint, not a block). The GET form of the URL still
works for URLs Google already knows, and it is disallowed in robots.txt. The
return path is same-site only, so the switch cannot be used as an open
redirect.

Hindi content is **not** separately indexable. Googlebot sends no cookie, so
it always sees English, and the site emits no `hreflang` alternates. That is
correct for a cookie-based switch. Indexable Hindi pages would need their own
URLs (for example `/hi/...`) with reciprocal `hreflang`. That is a product
decision, not a fix.

### robots.txt

`/robots.txt` is generated (`RobotsController`, `CrawlerPolicy`,
`resources/views/robots.blade.php`). A crawler obeys **only** the group that
names it (RFC 9309), so every group repeats the private paths. A `Disallow`
under `User-agent: *` alone does nothing for Googlebot when Googlebot has a
group of its own. robots.txt keeps crawlers off **action** URLs
(`/language/`, `/loans/*/apply$`, the funnel). It is not a way to remove pages
from the index: Google must be able to crawl a URL to see its redirect,
canonical or `noindex`.

## Production changes that need hosting access

These cannot be done from the repository. Back up anything before changing it.

1. **Remove the static robots.txt on the server.** Production serves a
   hand-written file that Apache returns before Laravel runs. It is most likely
   `public/robots.txt` in the deployed app; it could also be an Apache `Alias`
   or a hosting-panel file. It has no
   `/language/` rule and no `Sitemap:` line, and its private-path rules sit
   under `*` while Googlebot and Bingbot have their own `Allow: /` groups.
   Back it up (`cp public/robots.txt ~/robots.txt.bak-$(date +%F)`), compare
   it with the generated output (`curl -s https://fynnedge.com/robots.txt`
   after removal, or render it locally), and re-add any bots you want treated
   specially in Admin → Website Settings → SEO & Tracking → extra directives.
   The static file also mentions `/llms.txt`. That file is separate and is not
   affected.
2. **Cloudflare: one redirect rule for all of `www`.** Today only the bare
   `https://www.fynnedge.com/` redirects. `http://www…` answers **522** and
   every other `https://www…` path answers **403** from the origin. In
   Cloudflare → Rules → Redirect Rules, create one rule:
   - When: `Hostname equals www.fynnedge.com`
   - Then: Dynamic redirect, expression
     `concat("https://fynnedge.com", http.request.uri.path)`, status **301**,
     **Preserve query string** on.
   - Make sure it runs before any existing www page rule, and remove the old
     root-only rule afterwards.
   Because the rule answers at the edge, it covers http and https and never
   reaches the origin. That fixes both the 522 and the 403.
3. Optional: give the Apache vhost a `ServerAlias www.fynnedge.com` with the
   same redirect, as a fallback in case the Cloudflare proxy is ever turned off.

## Pre-deployment checklist

- [ ] `php artisan test --compact` passes. At minimum run
      `tests/Feature/RedirectsTest.php`, `SiteLanguageTest.php`,
      `SitemapTest.php`, `Seo/CrawlerPolicyTest.php` and `SeoAuditCommandTest.php`.
- [ ] `vendor/bin/pint --dirty --format agent` is clean.
- [ ] No `<a href>` to `/language/` anywhere (`grep -rn "locale.switch" resources/views`).
- [ ] `public/.htaccess` has no trailing-slash rewrite.
- [ ] Any new redirect rows point at a page that answers 200, not at another
      redirect, the homepage, or a listing page.
- [ ] Back up the server's `public/.htaccess` and, if present, `public/robots.txt`.

## Deploy

1. Pull the release and run `composer install --no-dev` and `npm run build` as usual.
2. `php artisan migrate --force` adds the two legacy redirect rows and skips
   any source that already has a row.
3. `php artisan optimize:clear` (or your usual cache step) so the redirect map
   and views are fresh.
4. Apply the production changes above (robots.txt, Cloudflare) if they are not already done.

## Post-deployment smoke test

Run on the server, where it can read the production redirect table:

```bash
php artisan seo:audit --base-url=https://fynnedge.com
```

It is read-only: it fetches robots.txt, the sitemap, every sitemap page, the
source of every active redirect row, and the http/www variants. It exits
non-zero and lists each problem. Use `--limit=50` for a quick pass. Then check
by hand:

```bash
curl -sI https://fynnedge.com/business-loan-3/ | grep -i -E '^(HTTP|location)'   # 301 -> /loans/business-loan
curl -sI https://fynnedge.com/credit-card/     | grep -i -E '^(HTTP|location)'   # 301 -> /loans/credit-card
curl -sI https://fynnedge.com/loan-central/    | head -1                          # 404, no redirect
curl -sI http://www.fynnedge.com/loans         | grep -i -E '^(HTTP|location)'   # 301 -> https://fynnedge.com/loans
curl -s  https://fynnedge.com/ | grep -c 'href="[^"]*/language/'                  # 0
```

Then switch the language in a browser (desktop strip and mobile menu) on a
page with a query string and confirm you come back to the same URL in the
other language. Also run a loan enquiry and an eligibility journey.

## Rollback

- Code: redeploy the previous release. If the old `.htaccess` comes back, its
  trailing-slash rule returns too. That is harmless, just the old behaviour.
- Migration: `php artisan migrate:rollback --step=1` removes only the two
  legacy rows, and only if they are unchanged. Or switch them off in Admin → Redirects.
- robots.txt: restore the backed-up static file.
- Cloudflare: disable the new rule and re-enable the old one.

## Search Console, after deploy

Search Console needs an authorised owner. Nothing in this repo talks to it.

1. **URL Inspection → Test live URL** on one URL from each group:
   `https://fynnedge.com/business-loan-3/` (one 301),
   `https://fynnedge.com/credit-card/` (one 301), `http://www.fynnedge.com/`
   (one 301, no 522), `https://fynnedge.com/loan-central/` (404), and a loan
   page (200, "User-declared canonical" equals itself).
2. **Sitemaps:** confirm `https://fynnedge.com/sitemap.xml` is submitted and
   reads successfully. Resubmit it once after deploy.
3. **Page indexing → Page with redirect → Validate fix:** only once the live
   checks above pass. Many URLs in that report are **meant** to redirect:
   `http://`, `www`, moved WordPress pages and `/language/…`. For those,
   "Page with redirect" is the correct, permanent status, not an error, and
   validation will keep listing them. What this work removes is the
   **source** of new ones (crawlable language links), the two-hop chains,
   and the redirects into 404s.
4. Expect weeks, not days. Google recrawls old URLs slowly, and known
   `/language/` URLs drop out gradually once nothing links to them. A
   technically correct site does not guarantee any URL gets indexed.
