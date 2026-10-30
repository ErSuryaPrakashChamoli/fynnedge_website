---
paths:
  - app/Models/Article.php
  - app/Models/Setting.php
---

# App Models

## Use Article::newestFirst() and publishedOn(), never published_at directly
published_at is optional in ArticleForm, so a live article can have it NULL. MySQL sorts NULLs last on ORDER BY published_at DESC, which pushed brand-new articles to the bottom of /resources, and the card's `@if ($article->published_at)` hid its date entirely. scopeNewestFirst() orders by coalesce(published_at, created_at) desc then id desc; publishedOn() returns the same fallback date for display and for JSON-LD datePublished. Any new article listing or date render must use these two, not the raw column. Callers: ArticleController::index, the EMI calculator's relatedArticles, NewsletterCampaignForm's article select, both resources views.

## Setting::get caches a NOT_STORED sentinel; write settings only through Setting::set
The cache cannot hold null (it reads back as a miss), so unset keys used to be re-queried and re-written to the cache table on every request: 91 queries + 91 writes per homepage view. Setting::get now caches NOT_STORED for a missing row, is memoized per request (Cache::memo()), and applies $default after the cache, never inside it. Consequence: a settings row written any other way (DB::table, Setting::query()->create, a data migration) stays invisible until `setting:{key}` is forgotten through Cache::memo()->forget() or the cache is cleared. Use Setting::set(). Pinned by SettingTest.
