<x-layouts.app :title="$article->seoTitle()" :description="$article->seoDescription()" :canonical="$article->seoCanonicalUrl()" :og-image="$article->seoOgImageUrl()" :robots="$article->seoRobots()">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Resources' => route('resources.index'), $article->title => null]" />

        @if ($article->published_at)
            <p class="mt-5 font-mono text-xs text-ink-faint">{{ $article->published_at->format('d M Y') }}</p>
        @endif
        <h1 data-reveal="up" class="mt-3 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $article->title }}
        </h1>
        @if ($article->excerpt)
            <p data-reveal="up" class="mt-3 text-lg text-ink-muted">{{ $article->excerpt }}</p>
        @endif

        @if ($article->body)
            <div data-reveal="fade" class="prose prose-neutral mt-10 max-w-none text-ink-muted [&_h2]:font-display [&_h2]:text-ink [&_h2]:mt-8 [&_p]:leading-relaxed">
                {!! $article->body !!}
            </div>
        @endif

        @php
            $publisherName = \App\Models\Setting::get('site_name', 'FynnEdge');
        @endphp
        <script type="application/ld+json">
            {!! json_encode(array_filter([
                '@@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $article->title,
                'description' => $article->excerpt,
                'datePublished' => $article->published_at?->toIso8601String(),
                'dateModified' => $article->updated_at->toIso8601String(),
                'image' => $article->seoOgImageUrl(),
                'author' => ['@type' => 'Organization', 'name' => $publisherName],
                'publisher' => ['@type' => 'Organization', 'name' => $publisherName],
                'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $article->seoCanonicalUrl() ?: url()->current()],
            ])) !!}
        </script>
    </section>
</x-layouts.app>
