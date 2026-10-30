---
paths:
  - 'public/.htaccess,app/Http/Middleware/HandleRedirects.php,app/Models/Redirect.php'
---

# Middleware Models

## Trailing/doubled slashes are normalised in HandleRedirects, not .htaccess
Do not restore Laravel's stock "Redirect Trailing Slashes" rewrite in public/.htaccess: running before the app it made /old/ → /old → row a 2-hop chain and /deleted/ a redirect to a 404 (Search Console "Page with redirect"). HandleRedirects matches the row first (Redirect::resolve() flattens chains, 302 if any hop is temporary, null on a loop), then 301s a slash variant only when its clean path matches a route; anything else 404s directly. Verify live with `php artisan seo:audit --base-url=https://fynnedge.com`.
