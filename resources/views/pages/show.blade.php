<x-layouts.app :title="$page->seoTitle()" :description="$page->seoDescription()">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="[$page->title => null]" />

        <h1 class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $page->title }}
        </h1>
        @if ($page->excerpt)
            <p class="mt-3 text-lg text-ink-muted">{{ $page->excerpt }}</p>
        @endif

        @if ($page->body)
            <div class="prose prose-neutral mt-10 max-w-none text-ink-muted [&_h2]:font-display [&_h2]:text-ink [&_h2]:mt-8 [&_p]:leading-relaxed">
                {!! $page->body !!}
            </div>
        @endif
    </section>
</x-layouts.app>
