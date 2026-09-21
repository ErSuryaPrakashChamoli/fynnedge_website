---
paths:
  - app/Models/Article.php
---

# App Models

## Use Article::newestFirst() and publishedOn(), never published_at directly
published_at is optional in ArticleForm, so a live article can have it NULL. MySQL sorts NULLs last on ORDER BY published_at DESC, which pushed brand-new articles to the bottom of /resources, and the card's `@if ($article->published_at)` hid its date entirely. scopeNewestFirst() orders by coalesce(published_at, created_at) desc then id desc; publishedOn() returns the same fallback date for display and for JSON-LD datePublished. Any new article listing or date render must use these two, not the raw column. Callers: ArticleController::index, the EMI calculator's relatedArticles, NewsletterCampaignForm's article select, both resources views.
