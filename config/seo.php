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

    /*
     * First-party page-view counts for Admin → Leads & Enquiries → Lead
     * Analytics (App\Http\Middleware\RecordPageView): one analytics_events row
     * per public HTML page view, written after the response is sent. No cookie,
     * IP address or user agent is stored, and bots are skipped — it counts
     * views per page, not people. retention_days bounds the table (daily prune).
     */
    'page_views' => [
        'enabled' => (bool) env('PAGE_VIEW_TRACKING_ENABLED', true),
        'retention_days' => (int) env('PAGE_VIEW_RETENTION_DAYS', 400),
    ],

    'not_found' => [
        'retention_days' => (int) env('SEO_NOT_FOUND_RETENTION_DAYS', 90),
        'max_tracked_paths' => (int) env('SEO_NOT_FOUND_MAX_PATHS', 5000),
    ],

];
