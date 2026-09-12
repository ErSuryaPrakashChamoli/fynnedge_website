<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $resolvedTitle = $title ?? $siteBranding['name'];
        $resolvedDescription = ($description ?? null) ?: \App\Support\Seo\SeoDefaults::metaDescription();
        $resolvedCanonical = \App\Support\Seo\SeoDefaults::canonical($canonical ?? null, url()->current());
        /*
         * Absolute, unlike every other image URL on the page: uploaded images
         * resolve relative to the current host (config/filesystems.php), which
         * is what keeps them working across localhost, staging and production,
         * but a crawler reading og:image/twitter:image/JSON-LD out of context
         * has nothing to resolve a relative path against.
         */
        $resolvedOgImage = \App\Support\Seo\SeoDefaults::absolute($ogImage ?? $siteBranding['defaultOgImageUrl']);

        /*
         * Open Graph and Twitter/X resolve through the same chain for every
         * page: this record's override (the :social prop) → the sitewide
         * default Setting → the page's own title/description. SeoDefaults owns
         * that order so it is applied identically everywhere, and so a blank
         * admin field means "follow the page" rather than an empty tag.
         */
        $resolvedSocial = $social ?? [];
        $resolvedOgTitle = ($resolvedSocial['ogTitle'] ?? null)
            ?: \App\Support\Seo\SeoDefaults::ogTitle($title ?? null, $resolvedTitle);
        $resolvedOgDescription = ($resolvedSocial['ogDescription'] ?? null)
            ?: \App\Support\Seo\SeoDefaults::ogDescription($description ?? null, $resolvedDescription);
        $resolvedTwitterTitle = ($resolvedSocial['twitterTitle'] ?? null) ?: $resolvedOgTitle;
        $resolvedTwitterDescription = ($resolvedSocial['twitterDescription'] ?? null) ?: $resolvedOgDescription;
        $resolvedTwitterImage = \App\Support\Seo\SeoDefaults::absolute(\App\Support\Seo\SeoDefaults::twitterImageUrl($resolvedSocial['twitterImageUrl'] ?? null, $resolvedOgImage));
        $resolvedTwitterCard = \App\Support\Seo\SeoDefaults::twitterCard($resolvedTwitterImage);

        /*
         * A record's admin-chosen @type wins over the type the view hardcodes,
         * which in turn wins over the sitewide default Setting. The schema
         * template attached to that record is rendered here rather than in the
         * view because this is the only place the final title, description,
         * canonical URL and social image are all resolved — the exact values
         * its placeholder tokens substitute.
         */
        $resolvedPageType = ($pageType ?? null) ?: \App\Support\Seo\SchemaGraph::defaultPageType();

        $resolvedSchemaNodes = $schemaNodes ?? [];

        if ($schemaTemplate ?? null) {
            $templateNode = \App\Support\Seo\SchemaTemplateRenderer::render(
                $schemaTemplate,
                \App\Support\Seo\SchemaTemplateRenderer::context(
                    title: $resolvedTitle,
                    description: $resolvedDescription,
                    url: $resolvedCanonical,
                    imageUrl: $resolvedOgImage,
                    siteName: $siteBranding['name'],
                ),
            );

            if ($templateNode) {
                $resolvedSchemaNodes[] = $templateNode;
            }
        }
    @endphp

    <title>{{ $resolvedTitle }}{{ isset($title) ? ' — '.$siteBranding['name'] : ' — '.$siteBranding['tagline'] }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    {{-- A page can only ever be LESS indexable than the sitewide setting: --}}
    {{-- SearchEngineIndexing forces noindex when the switch is off, whatever this page asked for. --}}
    <meta name="robots" content="{{ \App\Support\Seo\SearchEngineIndexing::metaRobots($robots ?? null) }}">
    <link rel="canonical" href="{{ $resolvedCanonical }}">

    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:site_name" content="{{ $siteBranding['name'] }}">
    <meta property="og:title" content="{{ $resolvedOgTitle }}">
    @if ($resolvedOgDescription)
        <meta property="og:description" content="{{ $resolvedOgDescription }}">
    @endif
    <meta property="og:url" content="{{ $resolvedCanonical }}">
    <meta property="og:locale" content="en_IN">
    @if ($resolvedOgImage)
        <meta property="og:image" content="{{ $resolvedOgImage }}">
    @endif

    <meta name="twitter:card" content="{{ $resolvedTwitterCard }}">
    <meta name="twitter:title" content="{{ $resolvedTwitterTitle }}">
    @if ($resolvedTwitterDescription)
        <meta name="twitter:description" content="{{ $resolvedTwitterDescription }}">
    @endif
    @if ($resolvedTwitterImage)
        <meta name="twitter:image" content="{{ $resolvedTwitterImage }}">
    @endif

    <link rel="icon" href="{{ $siteBranding['faviconUrl'] }}" sizes="any">
    @unless ($siteBranding['faviconIsCustom'])
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    @endunless
    <link rel="apple-touch-icon" href="{{ $siteBranding['faviconIsCustom'] ? $siteBranding['faviconUrl'] : asset('apple-touch-icon.png') }}">

    {{--
        One sitewide JSON-LD graph: Organization + WebSite + the WebPage being
        viewed, cross-referenced by stable @id. Assembled in
        App\Support\Seo\SchemaGraph rather than inline, because '@context'-style
        array keys are Blade directives inside a template, and because a
        controller can append page-specific nodes (Service, ...) to the same
        graph via :schema-nodes instead of emitting a second, disconnected copy
        of the organization.
    --}}
    <script type="application/ld+json">
        {!! json_encode(
            \App\Support\Seo\SchemaGraph::build(
                siteName: $siteBranding['name'],
                logoUrl: \App\Support\Seo\SeoDefaults::absolute($siteBranding['logoUrl']),
                pageTitle: $resolvedTitle,
                pageDescription: $resolvedDescription,
                canonicalUrl: $resolvedCanonical,
                ogImageUrl: $resolvedOgImage,
                pageType: $resolvedPageType,
                extraNodes: $resolvedSchemaNodes,
            ),
            JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) !!}
    </script>

    {{--
        Admin-authored JSON-LD from the record's SEO section, for schema.org
        types this app doesn't generate itself. It's stored decoded (a json
        column cast to array), so it can only ever re-encode as valid JSON,
        and JSON_HEX_TAG escapes < and > so a stray "</script>" inside any
        string value can't break out of the tag.
    --}}
    @if (filled($structuredData ?? null))
        <script type="application/ld+json">
            {!! json_encode($structuredData, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endif

    {{--
        Verification, analytics and admin-authored head scripts, from
        Admin → Website Settings → SEO & Analytics. Rendered unescaped because
        they are script markup; assembled (and their IDs validated) in
        App\Support\Analytics\TrackingScripts, which is the ONLY place these
        may be rendered — one render site is what keeps a tag from appearing
        twice on a page.
    --}}
    {!! $trackingHead !!}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {!! $siteThemeStyles !!}
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-bg font-sans text-ink antialiased">
    {{-- Google Tag Manager's <noscript> has to be the first thing inside <body>. --}}
    {!! $trackingBodyStart !!}

    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:shadow-lg">
        Skip to content
    </a>

    <x-site.header />

    <main id="main-content" class="flex-1">
        {{ $slot }}

        {{-- Views that render their own FAQ section pass handles-faqs and merge --}}
        {{-- the pinned FAQs into it themselves; see x-site.page-faqs. --}}
        @unless ($handlesFaqs ?? false)
            <x-site.page-faqs />
        @endunless
    </main>

    <x-site.footer />

    {{-- Only rendered while the banner is switched on and this visitor has not answered it. --}}
    <x-site.cookie-consent />

    @livewireScripts

    {!! $trackingBodyEnd !!}
</body>
</html>
