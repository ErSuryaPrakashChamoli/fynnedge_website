---
paths:
  - 'app/Support/Seo/Sitemap.php,app/Models/PageSeo.php,app/Filament/Schemas/SeoFormSection.php'
---

# Models Filament Schemas

## Sitemap applies Page SEO overrides in resolve(), with the layout's precedence
Supersedes "fromRecords() is the single place" in components-layouts.md: every source now returns candidates {url, robots, canonical, lastmod}, and Sitemap::resolve() is the ONE place that applies an active PageSeo row (its filled robots/canonical win, blank falls through, same as app.blade.php), drops noindex, and swaps in the canonical. Add new sources as candidates, never pre-filtered. /about and /careers are listed from their `pages` rows (CONTROLLER_PAGE_SLUGS), not as static entries. seo_metas.robots is nullable (default NULL = index, follow on a record, "don't override" on a PageSeo); SeoFormSection::make(defaultRobots: null) is the override-layer form, and record forms keep the 'index, follow' default.
