@if (! $indexable)
# Search engine indexing is switched OFF for this site
# (Admin → Website Settings → SEO & Tracking). Every page also sends
# "noindex, nofollow" in its head and in the X-Robots-Tag header.
User-agent: *
Disallow: /
@else
@php
    /*
     * A crawler obeys ONLY the one group that names it most specifically and
     * ignores every other group, "*" included (RFC 9309, and Google's own
     * robots.txt rules). So the private paths are repeated in every group that
     * allows crawling. Listed once under "*" alone, they bound nobody with a
     * group of its own: Googlebot and Bingbot read "Allow: /" and crawled
     * /language/, /admin and the application funnel freely.
     */
    $sharedRules = [
        ...array_map(fn (string $path): string => "Disallow: {$path}", $disallowedPaths),
        ...array_map(fn (string $path): string => "Allow: {$path}", $allowedPaths),
    ];
@endphp
# {!! $siteName !!}
# Public marketing content is open to search engines.
# Authenticated, transactional and system routes are not: the admin panel,
# authentication, the per-visitor application funnel, the language switch,
# storage internals, the health probe and signed draft previews.
@if ($allowedPaths)
# The credit score pages opted in to search (Admin → Website Settings →
# Credit Score Page) are allowed by the longer, more specific Allow lines.
@endif
#
# A crawler reads only the group that names it, so every group repeats
# the same private paths.

# --- Answer engines / AI search ---
@foreach ($aiCrawlers as $crawler => $crawlDelay)
User-agent: {{ $crawler }}
@if ($aiCrawlersAllowed)
Allow: /
@if ($crawlDelay)
Crawl-delay: {{ $crawlDelay }}
@endif
@foreach ($sharedRules as $rule)
{!! $rule !!}
@endforeach
@else
Disallow: /
@endif

@endforeach
# --- Search engines, and everything else ---
@foreach ($searchCrawlers as $crawler)
User-agent: {{ $crawler }}
@endforeach
User-agent: *
Allow: /
@foreach ($sharedRules as $rule)
{!! $rule !!}
@endforeach
@if ($extraDirectives)

# --- Added in Admin → Website Settings → SEO & Tracking ---
{!! $extraDirectives !!}
@endif

Sitemap: {!! $sitemapUrl !!}
@endif
