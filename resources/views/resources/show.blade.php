<x-layouts.app :title="$article->seoTitle()" :description="$article->seoDescription()" :canonical="$article->seoCanonicalUrl()" :og-image="$article->seoOgImageUrl()" :social="$article->seoSocial()" :robots="$article->seoRobots()" :structured-data="$article->seoStructuredData()" :page-type="$article->seoPageType()" :schema-template="$article->seoSchemaTemplate()" og-type="article">
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

        {{-- Newsletter CTA: after the article, before the schema block, so it reads
             as the natural next step rather than interrupting the article. --}}
        <x-site.newsletter-form
            class="mt-12"
            source="blog"
            :source-url="route('resources.show', $article, absolute: false)"
            heading="Enjoyed this article?"
            description="Get practical financial insights, loan tips and useful money guidance from FynnEdge directly in your inbox."
        />

        <script type="application/ld+json">
            {!! json_encode(array_filter([
                '@@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $article->title,
                'description' => $article->excerpt,
                'datePublished' => $article->published_at?->toIso8601String(),
                'dateModified' => $article->updated_at->toIso8601String(),
                'image' => $article->seoOgImageUrl(),
                'author' => ['@id' => \App\Support\Seo\SchemaGraph::organizationId()],
                'publisher' => ['@id' => \App\Support\Seo\SchemaGraph::organizationId()],
                'mainEntityOfPage' => ['@id' => ($article->seoCanonicalUrl() ?: url()->current()).'#webpage'],
            ])) !!}
        </script>
    </section>
</x-layouts.app>
