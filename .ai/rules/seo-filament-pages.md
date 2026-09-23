---
paths:
  - 'app/Support/Seo/SearchEngineIndexing.php,config/seo.php,app/Filament/Pages/SeoAnalytics.php'
---

# Seo Filament Pages

## SEO_INDEXING_ENABLED=false is a hard OFF the database cannot override
Supersedes the "Default is indexable (config/seo.php ← SEO_INDEXING_ENABLED)" line in nginx.md. SearchEngineIndexing::enabled() = config('seo.indexing_enabled') AND Setting 'seo_indexing_enabled' (default true). So a production DB restored onto staging (setting ON) stays noindexed when staging's .env says false. While forcedOffByEnvironment(), the SEO & Tracking toggle is disabled (so it isn't dehydrated and save() leaves the stored value alone). Never set SEO_INDEXING_ENABLED=false in production's .env; leaving it unset means "the admin setting decides".
