<x-layouts.app :title="$page->seoTitle()" :description="$page->seoDescription()" :canonical="$page->seoCanonicalUrl()" :og-image="$page->seoOgImageUrl()" :robots="$page->seoRobots()" :structured-data="$page->seoStructuredData()">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="[$page->title => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $page->title }}
        </h1>
        @if ($page->excerpt)
            <p data-reveal="up" class="mt-3 text-lg text-ink-muted">{{ $page->excerpt }}</p>
        @endif

        @if ($page->body)
            <div data-reveal="fade" class="prose prose-neutral mt-10 max-w-none text-ink-muted [&_h2]:font-display [&_h2]:text-ink [&_h2]:mt-8 [&_p]:leading-relaxed">
                {!! $page->body !!}
            </div>
        @endif

        <div class="mt-12">
            <h2 data-reveal="left" class="font-display text-xl font-semibold text-ink">Open positions</h2>

            @if ($jobOpenings->isEmpty())
                <p data-reveal="up" class="mt-3 text-sm text-ink-muted">
                    We don't have any open positions listed right now. If you'd like to be considered for future
                    roles, write to us using the details on our <a href="{{ route('contact') }}" class="text-accent hover:underline">Contact</a> page and tell us a bit about what you're looking for.
                </p>
            @else
                <div class="mt-4 flex flex-col divide-y divide-line border-y border-line">
                    @foreach ($jobOpenings as $opening)
                        <div data-reveal="left stagger" class="flex items-center justify-between gap-4 py-4">
                            <p class="font-medium text-ink">{{ $opening->title }}</p>
                            <x-ui.button tag="a" :href="route('contact')" variant="secondary" size="sm" class="shrink-0">
                                Apply
                            </x-ui.button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
