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

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteBranding['name'] }}">
    <meta property="og:title" content="{{ $resolvedTitle }}">
    <meta property="og:description" content="{{ $resolvedDescription }}">
    <meta property="og:url" content="{{ $resolvedCanonical }}">
    @if ($resolvedOgImage)
        <meta property="og:image" content="{{ $resolvedOgImage }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif

    <link rel="icon" href="{{ $siteBranding['faviconUrl'] }}" sizes="any">
    @unless ($siteBranding['faviconIsCustom'])
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    @endunless
    <link rel="apple-touch-icon" href="{{ $siteBranding['faviconIsCustom'] ? $siteBranding['faviconUrl'] : asset('apple-touch-icon.png') }}">

    {{--
        Site-wide Organization + WebSite structured data — every property here
        comes from the same Setting-backed values already shown in the header/
        footer, so it can never drift from or fabricate what's actually
        configured. Rendered once, sitewide, rather than per-page.
    --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@graph' => [
                array_filter([
                    '@type' => 'Organization',
                    '@id' => url('/').'#organization',
                    'name' => $siteBranding['name'],
                    'url' => url('/'),
                    'logo' => $siteBranding['logoUrl'],
                ]),
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#website',
                    'name' => $siteBranding['name'],
                    'url' => url('/'),
                ],
            ],
        ]) !!}
    </script>

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
