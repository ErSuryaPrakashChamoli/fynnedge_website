---
paths:
  - 'routes/web.php,app/Support/Seo/Sitemap.php,app/Filament/Resources/Pages/**,app/Http/Middleware/RecordPageView.php'
---

# Pages Http Middleware

## General pages catch-all /{slug} must stay the LAST route; page views are counted cookie-free
Before 2026-11-24 a Page with a slug outside Sitemap::ROUTED/CONTROLLER_PAGE_SLUGS had no public URL. Now `Route::get('/{slug}')->name('pages.show')` (pattern GENERAL_PAGE_SLUG_PATTERN) at the very end of routes/web.php serves other published pages; every earlier route wins. Sitemap::servesGeneralPage() checks the router actually reaches pages.show (e.g. /up, /contact, /loans are refused) and PageForm rejects such slugs and slugs an active Redirect takes. Use Sitemap::pageRoute()/pageUrl() for any page link or preview. RecordPageView (web group) writes analytics_events event_key=page_view with only {path, referrer_host, utm_*} after the response — no cookie, IP or UA; bots/prefetch/signed previews/portal skipped; pruned after seo.page_views.retention_days via the daily model:prune (page_view rows only).
