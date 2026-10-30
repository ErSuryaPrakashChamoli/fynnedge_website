---
paths:
  - 'app/Support/ContentCache/**,app/Enums/ContentCacheSection.php,app/Models/Concerns/InvalidatesContentCache.php'
---

# Models Concerns

## Cache public content only through ContentCache, never Cache::remember
Shared public data (settings map, mega menu, RateBook rows, redirects, PageSeo map, calculator/footer product rows) is cached with ContentCache::remember(name, [sections], build, scheduled: [Publishable models]). Section versions live in the content_cache_versions TABLE (not Redis) and move after commit, so an eviction/outage never loses an invalidation; values must be arrays/scalars (cache.serializable_classes=false; cache rows and hydrate models). New cached data = add its tables to ContentCacheSection::forTable(), use InvalidatesContentCache on the models, and call ContentCache::invalidateTable() from any write that skips events (DB::table backfills, saveQuietly, Filament ->reorderable()). Pass scheduled: for any published() set so scheduled publish/expiry shows without an edit. Never Cache::flush(); use content-cache:refresh {section}. Diagnose with content-cache:status. docs/redis-cache.md has the dependency map.
