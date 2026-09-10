<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Seo\CrawlerPolicy;
use App\Support\Seo\SearchEngineIndexing;
use Illuminate\Http\Response;

/**
 * /robots.txt is served by Laravel, not as a static public/robots.txt file, so
 * that it stays consistent with the sitewide indexing switch and the crawler
 * policy — a static file cannot say "Disallow: /" the moment an admin turns
 * indexing off, nor drop the AI crawlers when they opt out of them.
 *
 * Because it is a route, nginx needs `try_files` on its `location =
 * /robots.txt` block (docker/nginx/nginx.conf); the stock block matched the
 * URI, found no file and returned 404 without ever reaching index.php.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        return response()
            ->view('robots', [
                'indexable' => SearchEngineIndexing::enabled(),
                /*
                 * robots.txt is plain text, so the view echoes these values raw —
                 * HTML-escaping would turn a path pattern like /*&signature= into
                 * /*&amp;signature= and quietly stop it matching anything. Newlines
                 * are stripped from the admin-set name for the same reason a
                 * directive line must stay a single line.
                 */
                'siteName' => str_replace(["\r", "\n"], ' ', (string) Setting::get('site_name', 'FynnEdge')),
                'sitemapUrl' => route('sitemap'),
                'aiCrawlers' => CrawlerPolicy::AI_CRAWLERS,
                'aiCrawlersAllowed' => CrawlerPolicy::aiCrawlersAllowed(),
                'searchCrawlers' => CrawlerPolicy::SEARCH_CRAWLERS,
                'disallowedPaths' => CrawlerPolicy::disallowedPaths(),
                'extraDirectives' => CrawlerPolicy::extraDirectives(),
            ])
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
