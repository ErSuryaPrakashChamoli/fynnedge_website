---
paths:
  - 'app/Http/Middleware/RecordNotFoundRequests.php,app/Support/NotFound/**,app/Models/NotFoundUrl.php,bootstrap/app.php,resources/views/errors/**'
---

# Errors

## 404 monitoring is aggregated, bounded and PII-free; noise gets the bare 404
RecordNotFoundRequests (global, outermost) counts 404s per UrlPath-normalised path in not_found_urls via defer()->always() — without always() a 4xx response skips deferred callbacks. Never add IP/user-agent/query-string columns; referrer is scheme://host[:port]/path only. NotFoundRequests::isNoise() (probes, assets, admin, livewire, .well-known, build) both skips recording AND makes bootstrap/app.php render errors.bare-404 instead of the branded errors/404 layout page; UNTRACKED_PREFIXES keeps token URLs (/newsletter/, /journey/) out. Bounded by config seo.not_found (max_tracked_paths, retention_days + daily model:prune). Error views render the full layout outside the web group, so AppServiceProvider shares an empty ViewErrorBag for components.layouts.app (the footer newsletter form reads $errors) — a new layout component that needs session/web-group data must tolerate its absence.
