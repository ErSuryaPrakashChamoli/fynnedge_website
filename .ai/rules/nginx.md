---
paths:
  - 'app/Http/Controllers/RobotsController.php,app/Http/Controllers/SitemapController.php,app/Support/Seo/SearchEngineIndexing.php,docker/nginx/nginx.conf'
---

# Nginx

## robots.txt is a Laravel route now, not a static public/ file
public/robots.txt was DELETED (2026-09-09). /robots.txt is served by RobotsController + resources/views/robots.blade.php so it can follow the `seo_indexing_enabled` setting, and its `Sitemap:` line is now route('sitemap') instead of the hardcoded production URL. This supersedes the "public/robots.txt is a static file (Apache serves it before Laravel)" line in .ai/rules/layouts-views.md.

Load-bearing: docker/nginx/nginx.conf's `location = /robots.txt` block needs `try_files $uri /index.php?$query_string`. Without it nginx matches the URI, finds no file and 404s without ever reaching the front controller. Apache needs nothing (public/.htaccess already falls through on !-f).

App\Support\Seo\SearchEngineIndexing is the one switch: OFF makes the layout's robots meta `noindex, nofollow` (a per-page `robots` value can only make a page LESS indexable, never more), makes SearchEngineIndexingHeader send X-Robots-Tag on the web group only, makes robots.txt `Disallow: /` with no Sitemap line, and 404s /sitemap.xml. Default is indexable (config/seo.php ← SEO_INDEXING_ENABLED) so a missing settings row can never de-index production.
