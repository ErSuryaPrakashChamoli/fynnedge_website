---
paths:
  - 'config/filesystems.php,resources/views/components/layouts/app.blade.php,tests/**'
---

# Views Components Layouts

## Uploaded-image URLs are host-relative; Storage::fake() hides that setting from tests
The 'public' disk sets 'url' => '/storage' (changed 2026-09-12), NOT APP_URL.'/storage'. Building it from APP_URL baked that env value into every <img src>: a server whose .env still held the local value served every uploaded image as http://localhost:8000/storage/..., and config:cache kept doing so after .env was fixed. A relative URL follows the request's own scheme/host/port, so one database is correct on localhost, staging and production.

Where an absolute URL is genuinely required — og:image, twitter:image, JSON-LD (crawlers have no page to resolve a relative path against) — use App\Support\Seo\SeoDefaults::absolute(), which wraps url() so it follows the request rather than a possibly-stale APP_URL, and passes already-absolute URLs through untouched. resources/views/components/layouts/app.blade.php applies it at the three boundaries ($resolvedOgImage, $resolvedTwitterImage, SchemaGraph logoUrl). Do not absolutise ordinary page <img src> values.

TESTING TRAP: Storage::fake('public') builds the fake disk with only a `root`, discarding the `url` config, so Laravel falls back to a relative /storage path no matter what config says. Every test touching an uploaded image therefore passed while production served absolute localhost URLs. A test asserting how an uploaded image URL is BUILT must not call Storage::fake() — use the real disk (url() needs no file to exist). See the host-relative test in tests/Feature/HomepageHeroBannerTest.php.
