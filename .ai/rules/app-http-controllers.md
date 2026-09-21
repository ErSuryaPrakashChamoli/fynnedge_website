---
paths:
  - 'app/Http/Controllers/**'
---

# App Http Controllers

## Sort Publishable lists by coalesce(published_at, created_at), never published_at alone
published_at is optional in the Filament forms (Article, LoanProduct, Page, etc.), so a record published without a date stores NULL and still counts as live. MySQL sorts NULLs last on ORDER BY published_at DESC, which made brand-new articles sink to the bottom of /resources. Public "newest first" lists must order by ->orderByRaw('coalesce(published_at, created_at) desc')->orderByDesc('id'), and must render the same fallback date rather than hiding the date when published_at is NULL. For Article this is already wrapped up as scopeNewestFirst() + publishedOn() — use those, not the raw column. See ArticleController::index and PublicResourcesTest.
