---
paths:
  - 'app/Support/Rates/RateBook.php,app/Console/Commands/RatesStatusCommand.php'
---

# Commands

## RateBook's cache is versioned and invalidated after commit — never Cache::forget inside a transaction
RateBook::flush() invalidates ContentCacheSection::Rates (the version lives in the content_cache_versions table and moves after commit, see App\Support\ContentCache\ContentCache) and clears this request's memo; cached rows are stored under the version read before the query and trusted only while it matches. live() is memoised per second + locale and quotesFor() groups it by offer once — the comparison table calls it per lender, which used to cost ~300ms a loan page. The first design (rememberForever + Cache::forget in the saved hook) ran INSIDE RateWorkflow's transaction, so a page view during a publish could re-cache pre-publish rows and serve them until the next change — a real risk when bulk-publishing hundreds of rates while crawlers hit the site. Pinned by "ignores a stale copy of the rates…" in PublicRateDisplayTest. Diagnose a loan page with no rates on a server with `php artisan rates:status [slug]` (published / awaiting approval / live in DB vs live in site cache, plus findings); `--refresh` bumps the version. RateBook::fresh() reads the DB bypassing the cache; use instance methods (->live(), ->headline()) on it — the static helpers always read the cached instance.
