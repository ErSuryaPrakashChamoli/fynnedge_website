<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $resolvedTitle = $title ?? $siteBranding['name'];
        $resolvedDescription = $description ?? 'FynnEdge helps you find the right lender for your personal loan, home loan, business loan or loan against property — with clear, upfront eligibility.';
        $resolvedCanonical = $canonical ?? url()->current();
        $resolvedOgImage = $ogImage ?? $siteBranding['defaultOgImageUrl'];
    @endphp

    <title>{{ $resolvedTitle }}{{ isset($title) ? ' — '.$siteBranding['name'] : ' — '.$siteBranding['tagline'] }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
    <link rel="canonical" href="{{ $resolvedCanonical }}">

    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:site_name" content="{{ $siteBranding['name'] }}">
    <meta property="og:title" content="{{ $resolvedTitle }}">
    <meta property="og:description" content="{{ $resolvedDescription }}">
    <meta property="og:url" content="{{ $resolvedCanonical }}">
    <meta property="og:locale" content="en_IN">

    <meta name="twitter:card" content="{{ $resolvedOgImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $resolvedTitle }}">
    <meta name="twitter:description" content="{{ $resolvedDescription }}">
    @if ($resolvedOgImage)
        <meta property="og:image" content="{{ $resolvedOgImage }}">
        <meta name="twitter:image" content="{{ $resolvedOgImage }}">
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
                logoUrl: $siteBranding['logoUrl'],
                pageTitle: $resolvedTitle,
                pageDescription: $resolvedDescription,
                canonicalUrl: $resolvedCanonical,
                ogImageUrl: $resolvedOgImage,
                pageType: $pageType ?? 'WebPage',
                extraNodes: $schemaNodes ?? [],
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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {!! $siteThemeStyles !!}
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-bg font-sans text-ink antialiased">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:shadow-lg">
        Skip to content
    </a>

    <x-site.header />

    <main id="main-content" class="flex-1">
        {{ $slot }}
    </main>

    <x-site.footer />

    @livewireScripts
</body>
</html>
