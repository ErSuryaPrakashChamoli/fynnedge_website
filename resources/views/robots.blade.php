@if (! $indexable)
# Search engine indexing is switched OFF for this site
# (Admin → Website Settings → SEO & Tracking). Every page also sends
# "noindex, nofollow" in its head and in the X-Robots-Tag header.
User-agent: *
Disallow: /
@else
# {!! $siteName !!}
# Public marketing content is open to search engines.
# Authenticated, transactional and system routes are not.

# --- Answer engines / AI search ---
@foreach ($aiCrawlers as $crawler => $crawlDelay)
User-agent: {{ $crawler }}
{{ $aiCrawlersAllowed ? 'Allow: /' : 'Disallow: /' }}
@if ($aiCrawlersAllowed && $crawlDelay)
Crawl-delay: {{ $crawlDelay }}
@endif

@endforeach
# --- Search engines ---
@foreach ($searchCrawlers as $crawler)
User-agent: {{ $crawler }}
Allow: /

@endforeach
# --- Everything else ---
User-agent: *
Allow: /

# Admin panel, authentication, the per-visitor application funnel,
# storage internals, the health probe and signed draft previews.
@foreach ($disallowedPaths as $path)
Disallow: {!! $path !!}
@endforeach
@if ($extraDirectives)

# --- Added in Admin → Website Settings → SEO & Tracking ---
{!! $extraDirectives !!}
@endif

Sitemap: {!! $sitemapUrl !!}
@endif
