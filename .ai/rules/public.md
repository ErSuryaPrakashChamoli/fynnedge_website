---
paths:
  - public/.htaccess
---

# Public

## Compression and long-lived caching live in public/.htaccess, keyed by mod_rewrite env marks
Production is Apache 2.4 (AlmaLinux) serving the apex directly, not through Cloudflare. Nothing was compressed, so every page's ~540KB HTML went over the wire raw. mod_deflate (with mod_filter) compresses text types. Cache-Control is set by mod_headers using env=CACHE_IMMUTABLE (existing /build/assets/ files, 1 year, immutable) and env=CACHE_MEDIA (existing /storage images, 30 days). The env vars come from RewriteRule [E=] guarded by -f, so a 404 is never cached. Use only FileInfo-override directives here, because the existing rewrite rules already prove that override is allowed. Do not add <If>/<FilesMatch> containers. Every block is IfModule-guarded.
