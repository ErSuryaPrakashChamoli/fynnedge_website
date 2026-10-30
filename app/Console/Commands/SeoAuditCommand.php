<?php

namespace App\Console\Commands;

use App\Models\Redirect;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The post-deploy smoke test for the URL problems Search Console reports,
 * run against the LIVE site over HTTP rather than inside the app, because the
 * problems it exists to catch live outside the code: a static public/robots.txt
 * left on the server shadowing the generated one, a hostname rule at Cloudflare
 * or in the Apache vhost, a redirect row added in the admin.
 *
 * Read-only: it requests only the robots.txt, the sitemap, the pages the
 * sitemap lists, the source of each active redirect row and a few hostname
 * variants. Links found on pages are checked against the redirect table, never
 * requested, so it cannot open journey sessions through Apply links. Its own
 * requests are not counted in the redirects' hit counters (HandleRedirects).
 *
 * Exits non-zero when anything is wrong, so it can gate a deploy checklist.
 */
class SeoAuditCommand extends Command
{
    public const USER_AGENT = 'FynnEdge-SeoAudit/1.0';

    protected $signature = 'seo:audit
                            {--base-url= : The site to audit, e.g. https://fynnedge.com (default: APP_URL)}
                            {--limit=0 : Request at most this many sitemap pages (0 requests them all)}';

    protected $description = 'Check a running site for what Search Console reports: robots.txt groups, sitemap URLs that redirect or fail, canonicals, crawlable action links, redirect chains and hostname variants';

    /**
     * @var array<int, array{0: string, 1: string, 2: string}> check, URL, problem
     */
    private array $findings = [];

    /**
     * Problem links found on pages, keyed by problem then link, with the first
     * page each link turned up on. The language switch alone puts a distinct
     * link on every page, so each problem is reported with a few examples and
     * a count rather than one row per link.
     *
     * @var array<string, array<string, string>>
     */
    private array $linkProblems = [];

    private const LINK_EXAMPLES = 5;

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('base-url') ?: config('app.url')), '/');

        if (! filter_var($base, FILTER_VALIDATE_URL) || ! in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $this->components->error('Give --base-url as a full http(s) URL, e.g. https://fynnedge.com');

            return self::FAILURE;
        }

        $this->components->info("Auditing {$base}");

        $this->checkRobots($base);
        $locs = $this->checkSitemap($base);
        $this->checkSitemapPages($base, $locs);
        $this->checkRedirects($base);
        $this->checkHostVariants($base, $locs);

        foreach ($this->linkProblems as $problem => $links) {
            foreach (array_slice($links, 0, self::LINK_EXAMPLES, true) as $link => $page) {
                $this->findings[] = ['internal link', $link, "{$problem} (on {$page})"];
            }

            if (count($links) > self::LINK_EXAMPLES) {
                $this->findings[] = ['internal link', '… '.(count($links) - self::LINK_EXAMPLES).' more', $problem];
            }
        }

        if ($this->findings === []) {
            $this->components->info('No problems found.');

            return self::SUCCESS;
        }

        $this->table(['Check', 'URL', 'Problem'], $this->findings);
        $this->components->error(count($this->findings).' problem(s) found.');

        return self::FAILURE;
    }

    /**
     * Googlebot obeys only the group that names it (RFC 9309), so the
     * language switch must be disallowed in THAT group, not merely somewhere
     * in the file.
     */
    private function checkRobots(string $base): void
    {
        $url = $base.'/robots.txt';
        $response = $this->fetch($url);

        if (! $response?->ok()) {
            $this->findings[] = ['robots.txt', $url, self::describe($response)];

            return;
        }

        if (! in_array('disallow: /language/', self::robotsRulesFor('Googlebot', $response->body()), true)) {
            $this->findings[] = ['robots.txt', $url, 'Googlebot\'s group does not disallow /language/ — is a static public/robots.txt on the server shadowing the generated one?'];
        }

        if (! preg_match('/^sitemap:\s*(\S+)/mi', $response->body(), $sitemap) || $sitemap[1] !== $base.'/sitemap.xml') {
            $this->findings[] = ['robots.txt', $url, "no Sitemap: line for {$base}/sitemap.xml"];
        }
    }

    /**
     * @return array<int, string> the distinct <loc> URLs
     */
    private function checkSitemap(string $base): array
    {
        $url = $base.'/sitemap.xml';
        $response = $this->fetch($url);

        if (! $response?->ok()) {
            $this->findings[] = ['sitemap', $url, self::describe($response)];

            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body());
        libxml_use_internal_errors($previous);

        if ($xml === false || $xml->getName() !== 'urlset') {
            $this->findings[] = ['sitemap', $url, 'is not a well-formed <urlset> document'];

            return [];
        }

        $locs = [];

        foreach ($xml->url as $entry) {
            $locs[] = trim((string) $entry->loc);
        }

        foreach (array_count_values($locs) as $loc => $count) {
            if ($count > 1) {
                $this->findings[] = ['sitemap', (string) $loc, "listed {$count} times"];
            }
        }

        $locs = array_values(array_unique($locs));

        foreach ($locs as $loc) {
            if ($problem = self::sitemapLocProblem($loc, $base)) {
                $this->findings[] = ['sitemap', $loc, $problem];
            }
        }

        $this->components->twoColumnDetail('Sitemap URLs', (string) count($locs));

        return $locs;
    }

    /**
     * @param  array<int, string>  $locs
     */
    private function checkSitemapPages(string $base, array $locs): void
    {
        $limit = max(0, (int) $this->option('limit'));
        $pages = $limit > 0 ? array_slice($locs, 0, $limit) : $locs;

        if ($pages === []) {
            return;
        }

        $this->withProgressBar($pages, function (string $loc) use ($base): void {
            $response = $this->fetch($loc);

            if ($response?->redirect()) {
                $this->findings[] = ['sitemap page', $loc, 'redirects ('.$response->status().') to '.$response->header('Location').' — list the final URL instead'];

                return;
            }

            if (! $response?->ok()) {
                $this->findings[] = ['sitemap page', $loc, self::describe($response)];

                return;
            }

            $html = $response->body();
            $canonical = self::canonicalOf($html);

            if ($canonical === null) {
                $this->findings[] = ['canonical', $loc, 'has no <link rel="canonical">'];
            } elseif (rtrim($canonical, '/') !== rtrim($loc, '/')) {
                $this->findings[] = ['canonical', $loc, "canonical points at {$canonical}"];
            }

            if (str_contains(strtolower($response->header('X-Robots-Tag').' '.self::metaRobotsOf($html)), 'noindex')) {
                $this->findings[] = ['sitemap page', $loc, 'is in the sitemap but says noindex'];
            }

            foreach (self::linkProblemsOn($html, $base) as $link => $problem) {
                $this->linkProblems[$problem][$link] ??= $loc;
            }
        });

        $this->newLine();
    }

    /**
     * Every active row must answer in one hop with its final destination, and
     * that destination must be a page, not another redirect or an error.
     */
    private function checkRedirects(string $base): void
    {
        $sources = array_keys(Redirect::activeMap());

        foreach ($sources as $source) {
            $url = $base.$source;
            $expected = Redirect::resolve($source);

            if ($expected === null) {
                $this->findings[] = ['redirect', $url, 'its rows form a loop, so the site serves the page and redirects nowhere'];

                continue;
            }

            $target = str_contains($expected['destination'], '://') ? $expected['destination'] : $base.$expected['destination'];
            $response = $this->fetch($url);

            if (! $response?->redirect()) {
                $this->findings[] = ['redirect', $url, 'expected a redirect to '.$target.', got '.self::describe($response)];

                continue;
            }

            $location = (string) $response->header('Location');

            if (rtrim($location, '/') !== rtrim($target, '/')) {
                $this->findings[] = ['redirect', $url, "goes to {$location}, expected {$target}"];

                continue;
            }

            if (parse_url($location, PHP_URL_HOST) !== parse_url($base, PHP_URL_HOST)) {
                continue;
            }

            $landing = $this->fetch($location);

            if ($landing?->redirect()) {
                $this->findings[] = ['redirect', $url, "chain: {$location} redirects again to ".$landing->header('Location')];
            } elseif (! $landing?->ok()) {
                $this->findings[] = ['redirect', $url, "destination {$location} ".self::describe($landing)];
            }
        }

        $this->components->twoColumnDetail('Active redirect rows', (string) count($sources));
    }

    /**
     * http://, http://www. and https://www. must each reach the canonical
     * https:// host in ONE permanent hop, path intact. Skipped for a local
     * host, which has no such variants.
     *
     * @param  array<int, string>  $locs
     */
    private function checkHostVariants(string $base, array $locs): void
    {
        $host = (string) parse_url($base, PHP_URL_HOST);

        if (parse_url($base, PHP_URL_SCHEME) !== 'https' || str_starts_with($host, 'www.') || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)) {
            $this->components->twoColumnDetail('Hostname variants', 'skipped (not a public https apex host)');

            return;
        }

        $deepPath = collect($locs)
            ->map(fn (string $loc): string => (string) parse_url($loc, PHP_URL_PATH))
            ->first(fn (string $path): bool => $path !== '' && $path !== '/') ?? '/loans';

        foreach (["http://{$host}", "http://www.{$host}", "https://www.{$host}"] as $variant) {
            foreach (['/', $deepPath] as $path) {
                $url = $variant.$path;
                $expected = $base.$path;
                $response = $this->fetch($url);

                if (! $response?->redirect()) {
                    $this->findings[] = ['hostname', $url, "expected a 301 to {$expected}, got ".self::describe($response)];
                } elseif ($response->status() !== 301) {
                    $this->findings[] = ['hostname', $url, 'redirects with '.$response->status().'; a hostname move is permanent, use 301'];
                } elseif (rtrim((string) $response->header('Location'), '/') !== rtrim($expected, '/')) {
                    $this->findings[] = ['hostname', $url, 'redirects to '.$response->header('Location').", not straight to {$expected}"];
                }
            }
        }
    }

    private function fetch(string $url): ?Response
    {
        try {
            return Http::withoutRedirecting()->withUserAgent(self::USER_AGENT)->timeout(20)->get($url);
        } catch (ConnectionException) {
            return null;
        }
    }

    private static function describe(?Response $response): string
    {
        return $response ? 'answers '.$response->status() : 'did not answer (connection failed or timed out)';
    }

    private static function sitemapLocProblem(string $loc, string $base): ?string
    {
        $parts = parse_url($loc);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return 'is not an absolute URL';
        }

        if (strtolower($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')) !== strtolower($base)) {
            return "is not on {$base}";
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            return 'carries a query string or fragment';
        }

        $path = $parts['path'] ?? '/';

        return match (true) {
            str_contains($path, '//') => 'contains a doubled slash',
            $path !== '/' && str_ends_with($path, '/') => 'ends in a slash, which the site redirects away',
            str_starts_with($path, '/language/') => 'is the language switch, an action rather than a page',
            default => null,
        };
    }

    /**
     * The rules (lowercased "allow: /x" / "disallow: /x") of the group a
     * crawler obeys: every group naming its product token, merged, or the "*"
     * groups when none does. A group is one or more User-agent lines followed
     * by rules; a User-agent line after a rule starts the next group.
     *
     * @return array<int, string>
     */
    private static function robotsRulesFor(string $crawler, string $robots): array
    {
        $groups = [];
        $current = null;

        foreach (preg_split('/\R/', $robots) as $line) {
            $line = trim((string) preg_replace('/#.*/', '', $line));

            if (! preg_match('/^([a-z-]+)\s*:\s*(.*)$/i', $line, $match)) {
                continue;
            }

            $field = strtolower($match[1]);
            $value = trim($match[2]);

            if ($field === 'user-agent') {
                if ($current === null || $groups[$current]['rules'] !== []) {
                    $groups[] = ['agents' => [], 'rules' => []];
                    $current = array_key_last($groups);
                }

                $groups[$current]['agents'][] = strtolower($value);
            } elseif (in_array($field, ['allow', 'disallow'], true) && $current !== null) {
                $groups[$current]['rules'][] = "{$field}: {$value}";
            }
        }

        $named = array_filter($groups, fn (array $group): bool => in_array(strtolower($crawler), $group['agents'], true));
        $applicable = $named ?: array_filter($groups, fn (array $group): bool => in_array('*', $group['agents'], true));
        $rules = [];

        foreach ($applicable as $group) {
            array_push($rules, ...$group['rules']);
        }

        return $rules;
    }

    private static function canonicalOf(string $html): ?string
    {
        preg_match_all('/<link\b[^>]*>/i', $html, $tags);

        foreach ($tags[0] as $tag) {
            if (preg_match('/\brel=["\']?canonical\b/i', $tag) && preg_match('/\bhref=["\']([^"\']+)["\']/i', $tag, $href)) {
                return html_entity_decode($href[1], ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    private static function metaRobotsOf(string $html): string
    {
        preg_match_all('/<meta\b[^>]*>/i', $html, $tags);

        foreach ($tags[0] as $tag) {
            if (preg_match('/\bname=["\']robots["\']/i', $tag) && preg_match('/\bcontent=["\']([^"\']*)["\']/i', $tag, $content)) {
                return $content[1];
            }
        }

        return '';
    }

    /**
     * Links on a page that make crawlers walk through a redirect or onto an
     * action URL. Only this site's own hosts are considered.
     *
     * @return array<string, string> absolute link => problem
     */
    private static function linkProblemsOn(string $html, string $base): array
    {
        $host = strtolower((string) parse_url($base, PHP_URL_HOST));
        $redirectSources = Redirect::activeMap();
        $problems = [];

        preg_match_all('/<a\b[^>]*?\bhref=["\']([^"\']+)["\']/i', $html, $matches);

        foreach (array_unique($matches[1]) as $href) {
            $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5);
            $url = str_starts_with($href, '/') && ! str_starts_with($href, '//') ? $base.$href : $href;
            $parts = parse_url($url);

            if (! is_array($parts) || ! in_array(strtolower($parts['host'] ?? ''), [$host, "www.{$host}"], true)) {
                continue;
            }

            $path = $parts['path'] ?? '/';
            $link = explode('#', $url)[0];

            $problem = match (true) {
                strtolower($parts['host']) !== $host || ($parts['scheme'] ?? '') !== parse_url($base, PHP_URL_SCHEME) => "links to a non-canonical host, not {$base}",
                str_starts_with($path, '/language/') => 'is a crawlable language-switch link; the switch must stay a POST form',
                $path !== '/' && (str_ends_with($path, '/') || str_contains($path, '//')) => 'links to a slash variant, which redirects',
                isset($redirectSources[Redirect::normalizePath($path)]) => 'links through a redirect row; link to its destination instead',
                default => null,
            };

            if ($problem !== null) {
                $problems[$link] = $problem;
            }
        }

        return $problems;
    }
}
