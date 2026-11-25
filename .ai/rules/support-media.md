---
paths:
  - 'app/Models/Author.php,app/Filament/Resources/Authors/**,app/Policies/AuthorPolicy.php,app/Filament/Resources/Articles/**,resources/views/resources/**,app/Support/Media/MediaRegistry.php'
---

# Support Media

## Article authors live in the `authors` table; articles.author_id is nullOnDelete
User approved the dedicated table (2026-11-24), replacing the interim settings storage. `authors` (public_id, slug unique, name/designation/bio with `_hi` siblings, photo_path on the public disk under authors/, profile_url https-only, is_active) and nullable `articles.author_id` → authors, ON DELETE SET NULL (deleting an author never deletes or hides an article). Public pages credit only `Article::publicAuthor()` (null for an inactive author, link kept); /resources/authors/{author:slug} 404s for inactive authors. Admin: AuthorResource at /admin/authors (same URL as the old screen); AuthorPolicy deliberately maps to Article permissions (Update:Article / Delete:Article) so no new permission rows are needed — `shield:generate --all` would overwrite it. Photos are a normal MediaRegistry::forModel entry. Migration 2026_11_24_130252 copied the legacy `blog_authors` / `blog_article_authors` settings (repeatable, reversible) and deliberately left both settings in place; delete them only in a later migration once production has been verified.
