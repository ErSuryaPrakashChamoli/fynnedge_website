<?php

namespace App\Support\NotFound;

use App\Console\Commands\SeoAuditCommand;
use App\Http\Middleware\HandleRedirects;
use App\Support\Seo\CrawlerPolicy;
use App\Support\UrlPath;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tells a missing PAGE (an old URL, a broken link: something to fix) apart
 * from the noise every public site gets: scanners probing for WordPress logins
 * and .env files, and stale asset URLs. Noise is answered with a bare 404
 * (no branded page, no database work) and is never recorded, so the Missing
 * pages list stays a list of things worth acting on.
 *
 * Legacy WordPress CONTENT URLs (/some-old-article/, /comments/feed/,
 * /2026/03/03/) are deliberately not noise: Google still crawls them, and each
 * one wants a redirect or a Gone rule.
 */
class NotFoundRequests
{
    /**
     * Files a page loads rather than pages themselves. A PDF is not listed:
     * a missing policy or rate-card PDF is a broken link someone clicked.
     */
    private const ASSET_EXTENSION = '/\.(css|js|mjs|map|png|jpe?g|gif|webp|avif|svg|ico|bmp|woff2?|ttf|otf|eot|mp4|webm|mp3)$/i';

    /**
     * Server-side scripts, archives and config files: this site serves none,
     * so a request for one is a scanner looking for something exploitable.
     */
    private const SCRIPT_EXTENSION = '/\.(php\d?|phtml|asp|aspx|ashx|jsp|cgi|pl|sh|sql|bak|old|orig|swp|zip|tar|gz|tgz|rar|7z|ini|log|ya?ml|conf|cfg|env)$/i';

    /**
     * Well-known probe targets, anywhere in the path.
     */
    private const PROBE = '#(^|/)(wp-(admin|login|includes|content|json|config|cron)|xmlrpc|phpmyadmin|pma|cgi-bin|vendor/phpunit|_ignition|telescope|actuator|\.(env|git|svn|hg|aws|ssh|ht|ds_store|vscode|idea))#i';

    /**
     * Not pages, or pages whose URL carries a token or a session id: a 404
     * there is either expected (an expired link) or would store a secret.
     *
     * @var array<int, string>
     */
    private const UNTRACKED_PREFIXES = [
        '/newsletter/', '/journey/', '/applications/', '/my-profile', '/existing-customer',
        '/language/', '/api/', '/livewire', '/.well-known/', '/build/',
    ];

    /**
     * Longer paths are scanners or junk; a real page URL is never this long.
     */
    private const MAX_PATH_LENGTH = 255;

    /**
     * A request whose 404 needs neither the branded page nor a record.
     */
    public static function isNoise(Request $request): bool
    {
        $path = $request->getPathInfo();
        $panelPath = HandleRedirects::panelPath();

        return preg_match(self::ASSET_EXTENSION, $path) === 1
            || preg_match(self::SCRIPT_EXTENSION, $path) === 1
            || preg_match(self::PROBE, $path) === 1
            || $request->is($panelPath, $panelPath.'/*', 'livewire*', '.well-known/*', 'build/*');
    }

    /**
     * Whether this request's 404 is counted in the Missing pages list.
     */
    public static function shouldRecord(Request $request): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        // seo:audit requests URLs on purpose to see how they answer.
        if ($request->userAgent() === SeoAuditCommand::USER_AGENT) {
            return false;
        }

        $path = self::pathOf($request);

        return strlen($path) <= self::MAX_PATH_LENGTH
            && ! Str::startsWith($path, self::UNTRACKED_PREFIXES)
            && ! self::isNoise($request);
    }

    /**
     * The path as the Missing pages list stores it: normalised the same way
     * redirect rows are, so /Old-Page/ and /old-page?x=1 are one row and a
     * rule added for it matches.
     */
    public static function pathOf(Request $request): string
    {
        return UrlPath::normalize($request->getPathInfo());
    }

    /**
     * The referrer as scheme://host[:port]/path, or null. Its query string is
     * dropped: on another site it can hold a search term or a session id.
     */
    public static function referrerOf(Request $request): ?string
    {
        $parts = parse_url((string) $request->headers->get('referer'));

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || ! isset($parts['host'])) {
            return null;
        }

        $origin = strtolower($parts['scheme'].'://'.$parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');

        return Str::limit($origin.($parts['path'] ?? '/'), self::MAX_PATH_LENGTH, '');
    }

    /**
     * Whether the visitor followed a link on this site to get here: a broken
     * internal link, the most urgent kind of 404.
     */
    public static function isInternalReferral(Request $request): bool
    {
        $referrerHost = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        $host = strtolower($request->getHost());

        return $referrerHost !== '' && in_array($referrerHost, [$host, "www.{$host}"], true);
    }

    public static function isSearchCrawler(Request $request): bool
    {
        $userAgent = strtolower((string) $request->userAgent());

        foreach (CrawlerPolicy::SEARCH_CRAWLERS as $crawler) {
            if (str_contains($userAgent, strtolower($crawler))) {
                return true;
            }
        }

        return false;
    }
}
