<x-layouts.app :title="'Resources'" description="Guides and articles to help you understand loans, eligibility and the application process.">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Resources' => null]" />

        <h1 data-reveal="up" class="mt-5 max-w-2xl text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Guides &amp; resources
        </h1>
        <p data-reveal="up" class="mt-3 max-w-xl text-ink-muted">
            Plain-language guides on loans, eligibility and the application process.
        </p>

        @if ($articles->isEmpty())
            <x-ui.alert tone="accent" class="mt-10">
                Guides are being added. Check back shortly.
            </x-ui.alert>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <a href="{{ route('resources.show', $article) }}" data-reveal="up stagger" class="group">
                        <x-ui.card class="card-lift h-full transition-colors transition-shadow group-hover:bg-accent-soft group-hover:shadow-md group-hover:animate-card-swing">
                            @if ($article->published_at)
                                <p class="font-mono text-xs text-ink-faint">{{ $article->published_at->format('d M Y') }}</p>
                            @endif
                            <p class="mt-3 font-display text-xl font-semibold text-ink group-hover:text-accent">{{ $article->title }}</p>
                            @if ($article->excerpt)
                                <p class="mt-2 text-sm text-ink-muted">{{ $article->excerpt }}</p>
                            @endif
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-accent">
                                Read more
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                            </span>
                        </x-ui.card>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
