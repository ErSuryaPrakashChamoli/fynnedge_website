---
paths:
  - app/Models/Article.php
  - app/Models/Setting.php
---

# App Models

## Use Article::newestFirst() and publishedOn(), never published_at directly
published_at is optional in ArticleForm, so a live article can have it NULL. MySQL sorts NULLs last on ORDER BY published_at DESC, which pushed brand-new articles to the bottom of /resources, and the card's `@if ($article->published_at)` hid its date entirely. scopeNewestFirst() orders by coalesce(published_at, created_at) desc then id desc; publishedOn() returns the same fallback date for display and for JSON-LD datePublished. Any new article listing or date render must use these two, not the raw column. Callers: ArticleController::index, the EMI calculator's relatedArticles, NewsletterCampaignForm's article select, both resources views.

## Setting::get reads one cached map of every setting; write settings through the model
Since 2026-10-30 every setting is loaded in one query and cached as a single key => value map in the content cache (ContentCacheSection::Settings); a page used to make ~90 per-key cache lookups (91 queries + 91 writes per homepage before the NOT_STORED fix). An unset key is just absent from the map, and $default is applied after the cache, never inside it. Any saved/deleted Setting model invalidates the map (InvalidatesContentCache), so Setting::set() and Setting::query()->create() both work. A row written with DB::table() or raw SQL stays invisible until `php artisan content-cache:refresh settings` or the TTL (CONTENT_CACHE_TTL, 1h). Pinned by SettingTest.
