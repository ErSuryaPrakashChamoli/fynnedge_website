<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine Indexing
    |--------------------------------------------------------------------------
    |
    | Used only when the `seo_indexing_enabled` setting has never been saved
    | from the admin panel, so a production site with a missing settings row
    | stays indexable rather than silently dropping out of search results.
    |
    | Set SEO_INDEXING_ENABLED=false in a staging or development .env to keep
    | that environment out of the index without an admin having to remember to
    | toggle it after every database refresh from production.
    |
    */

    'indexing_enabled' => (bool) env('SEO_INDEXING_ENABLED', true),

];
