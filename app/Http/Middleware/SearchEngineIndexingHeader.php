<?php

namespace App\Http\Middleware;

use App\Support\Seo\SearchEngineIndexing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends `X-Robots-Tag: noindex, nofollow` while sitewide indexing is switched
 * off in Admin → Website Settings → SEO & Analytics.
 *
 * The header exists alongside the layout's robots meta because the two cover
 * different responses: the meta only reaches crawlers that parse HTML, while
 * the header also covers /sitemap.xml, /robots.txt, PDFs and any other
 * non-HTML response the site serves.
 *
 * Registered on the `web` group only (bootstrap/app.php). The admin panel
 * declares its own middleware array and deliberately does NOT get this — /admin
 * is kept out of search by authentication and by robots.txt, and its
 * indexability is not an editor-controlled setting.
 */
class SearchEngineIndexingHeader
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! SearchEngineIndexing::enabled()) {
            $response->headers->set('X-Robots-Tag', SearchEngineIndexing::NOINDEX);
        }

        return $response;
    }
}
