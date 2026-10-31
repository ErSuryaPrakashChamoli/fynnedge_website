<?php

namespace App\Http\Middleware;

use App\Models\NotFoundUrl;
use App\Support\NotFound\NotFoundRequests;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * Counts every genuine 404 in the Missing pages list (not_found_urls), so an
 * old URL that Google or a visitor still asks for shows up in the admin
 * instead of only in a Search Console report weeks later.
 *
 * GLOBAL, like HandleRedirects: a URL that matches no route never reaches the
 * `web` group. It reads the finished response, so it sees a 404 whatever
 * raised it: no route, a route-bound record that does not exist, or a
 * controller's own abort(404). A Gone rule's 410 is intentional and is not
 * counted here (its row has its own hit counter).
 *
 * The write happens after the response is sent (defer, ->always() because a
 * 404 is not a "successful" response), so a missing page answers no slower.
 */
class RecordNotFoundRequests
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND && NotFoundRequests::shouldRecord($request)) {
            $path = NotFoundRequests::pathOf($request);
            $referrer = NotFoundRequests::referrerOf($request);
            $isInternalReferral = NotFoundRequests::isInternalReferral($request);
            $isCrawler = NotFoundRequests::isSearchCrawler($request);

            defer(fn () => NotFoundUrl::record($path, $referrer, $isInternalReferral, $isCrawler))->always();
        }

        return $response;
    }
}
