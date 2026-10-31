<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search Engine Indexing (environment switch)
    |--------------------------------------------------------------------------
    |
    | true (the default, also when unset): the admin setting "Allow search
    | engine indexing" decides, and a missing settings row means indexable, so
    | production never silently drops out of search results.
    |
    | false: the site is noindexed no matter what the admin setting says. Set
    | SEO_INDEXING_ENABLED=false in a staging or development .env so a database
    | copied from production can never make that environment indexable.
    |
    */

    'indexing_enabled' => (bool) env('SEO_INDEXING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Missing page (404) monitoring
    |--------------------------------------------------------------------------
    |
    | Every 404 a visitor or crawler meets is counted per path in the
    | not_found_urls table (Admin → Missing pages). Paths not seen for
    | retention_days are pruned daily; once max_tracked_paths distinct paths
    | are stored, new ones are no longer added (existing ones keep counting),
    | so a scanner walking random URLs cannot grow the table without limit.
    |
    */

    'not_found' => [
        'retention_days' => (int) env('SEO_NOT_FOUND_RETENTION_DAYS', 90),
        'max_tracked_paths' => (int) env('SEO_NOT_FOUND_MAX_PATHS', 5000),
    ],

];
