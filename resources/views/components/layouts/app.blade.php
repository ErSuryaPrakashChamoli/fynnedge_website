<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'FynnEdge' }}{{ isset($title) ? ' — FynnEdge' : ' — Simplifying Loan, Amplifying Trust' }}</title>
    <meta name="description" content="{{ $description ?? 'FynnEdge helps you find the right lender for your personal loan, home loan, business loan or loan against property — with clear, upfront eligibility.' }}">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
</body>
</html>
