<?php

/*
|--------------------------------------------------------------------------
| Public content cache
|--------------------------------------------------------------------------
|
| App\Support\ContentCache\ContentCache: settings, the loan mega menu,
| RateBook's rates, redirects and per-URL SEO overrides, cached in the
| default cache store (Redis in production) under versioned keys. Editing
| content invalidates its section immediately; see docs/redis-cache.md.
|
*/

return [

    /*
     * The rollback lever. Off, every lookup reads the database exactly as it
     * did before this cache existed; nothing is read from or written to the
     * store. Takes effect on the next request (after config:cache, if used).
     */
    'enabled' => (bool) env('CONTENT_CACHE_ENABLED', true),

    /*
     * A cache store from config/cache.php; null means the default store
     * (CACHE_STORE).
     */
    'store' => env('CONTENT_CACHE_STORE'),

    /*
     * Seconds an entry may live. A safety net only: a content change moves
     * the section's version at once, so this bounds how long a copy could
     * outlive a change made behind the application's back (raw SQL on the
     * server) and lets superseded versions expire out of Redis.
     */
    'ttl' => (int) env('CONTENT_CACHE_TTL', 3600),

];
