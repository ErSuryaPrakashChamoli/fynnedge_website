<x-layouts.app :title="$article->seoTitle()" :description="$article->seoDescription()">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Resources' => route('resources.index'), $article->title => null]" />

        @if ($article->published_at)
            <p class="mt-5 font-mono text-xs text-ink-faint">{{ $article->published_at->format('d M Y') }}</p>
        @endif
        <h1 class="mt-3 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $article->title }}
        </h1>
        @if ($article->excerpt)
            <p class="mt-3 text-lg text-ink-muted">{{ $article->excerpt }}</p>
        @endif

        @if ($article->body)
            <div class="prose prose-neutral mt-10 max-w-none text-ink-muted [&_h2]:font-display [&_h2]:text-ink [&_h2]:mt-8 [&_p]:leading-relaxed">
                {!! $article->body !!}
            </div>
        @endif
    </section>
</x-layouts.app>
