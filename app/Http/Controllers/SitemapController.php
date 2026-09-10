<?php

namespace App\Http\Controllers;

use App\Support\Seo\SearchEngineIndexing;
use App\Support\Seo\Sitemap;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        /*
         * A sitemap exists for exactly one purpose: inviting crawlers to index
         * these URLs. Serving one while the sitewide indexing switch is off
         * would contradict the noindex the same pages send, so the sitemap
         * disappears with it — /robots.txt drops its Sitemap: line at the same
         * time, from the same setting.
         */
        abort_unless(SearchEngineIndexing::enabled(), 404);

        return response()
            ->view('sitemap', ['entries' => Sitemap::entries()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
