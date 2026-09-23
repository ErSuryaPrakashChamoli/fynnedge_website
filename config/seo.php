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

];
