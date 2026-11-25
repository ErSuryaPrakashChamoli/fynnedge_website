<?php

namespace App\Http\Middleware;

use App\Models\LoanProduct;
use App\Modules\Analytics\Enums\AnalyticsEventKey;
use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Support\UrlPath;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

use function Illuminate\Support\defer;

/**
 * Counts views of public pages, so Lead Analytics can show real conversion
 * rates (enquiries ÷ views per page) instead of estimates.
 *
 * Privacy by construction: one analytics_events row per view holding only the
 * page path, the loan product (for loan pages), the referring site's host and
 * any utm_* tags — no cookie is set or read, and no IP address, user agent or
 * full referrer URL is kept. It counts views, not people; there is no
 * "unique visitors" figure because nothing identifies a visitor.
 *
 * Counted: successful (200) GET HTML pages in the `web` group. Skipped:
 * crawlers and other bots, link prefetches, signed preview links, the
 * customer portal and token URLs. The insert runs after the response is sent.
 */
class RecordPageView
{
    private const BOT = '/bot|crawl|spider|slurp|archiver|facebookexternalhit|embedly|preview|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|python|curl|wget|httpclient|okhttp|axios|go-http|java\/|php\//i';

    private const UNTRACKED_PREFIXES = ['/my-profile', '/existing-customer', '/journey/', '/applications/', '/newsletter/', '/language/', '/livewire', '/api/'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            $properties = $this->properties($request);
            $loanProductId = $this->loanProductId($request);

            defer(fn () => AnalyticsEvent::query()->create([
                'event_key' => AnalyticsEventKey::PageView,
                'loan_product_id' => $loanProductId,
                'properties' => $properties,
            ]));
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! config('seo.page_views.enabled', true) || ! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html') || $request->ajax() || $request->hasHeader('X-Livewire')) {
            return false;
        }

        if ($request->query->has('signature') || in_array(strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose'))), ['prefetch', 'prefetch;prerender', 'preview'], true)) {
            return false;
        }

        $userAgent = (string) $request->userAgent();

        if ($userAgent === '' || preg_match(self::BOT, $userAgent) === 1) {
            return false;
        }

        return ! Str::startsWith(UrlPath::normalize($request->getPathInfo()), self::UNTRACKED_PREFIXES);
    }

    /**
     * @return array<string, string>
     */
    private function properties(Request $request): array
    {
        $referrerHost = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        $host = strtolower($request->getHost());

        return array_filter([
            'path' => Str::limit(UrlPath::normalize($request->getPathInfo()), 255, ''),
            'referrer_host' => $referrerHost !== '' && ! in_array($referrerHost, [$host, "www.{$host}"], true) ? Str::limit($referrerHost, 120, '') : null,
            'utm_source' => $this->tag($request, 'utm_source'),
            'utm_medium' => $this->tag($request, 'utm_medium'),
            'utm_campaign' => $this->tag($request, 'utm_campaign'),
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }

    private function tag(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? Str::limit(strip_tags($value), 100, '') : null;
    }

    private function loanProductId(Request $request): ?int
    {
        $product = $request->route('loanProduct');

        return $product instanceof LoanProduct ? (int) $product->getKey() : null;
    }
}
