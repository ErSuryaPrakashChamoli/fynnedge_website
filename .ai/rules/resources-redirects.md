---
paths:
  - 'app/Support/UrlPath.php,app/Http/Middleware/HandleRedirects.php,app/Filament/Resources/Redirects/**'
---

# Resources Redirects

## UrlPath::isRoutable counts the /{slug} catch-all only for a published page
Since the general-page catch-all (pages.show, /{slug}) exists, almost any one-segment path "matches a route". UrlPath::isRoutable() therefore treats a pages.show match as routable ONLY when a published Page with that slug exists. Without this, HandleRedirects 301'd "/unknown/" to "/unknown" and then 404'd (a 2-hop "Page with redirect" in Search Console; caught by RedirectsTest), and RedirectForm accepted redirect destinations that do not exist. Keep this check if the catch-all route changes.
