<x-layouts.app :title="$page->seoTitle()" :description="$page->seoDescription()" :canonical="$page->seoCanonicalUrl()" :og-image="$page->seoOgImageUrl()" :robots="$page->seoRobots()">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="[$page->title => null]" />

        <h1 data-reveal="up" class="mt-5 max-w-2xl text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $page->title }}
        </h1>
        @if ($page->excerpt)
            <p data-reveal="up" class="mt-3 max-w-xl text-lg text-ink-muted">{{ $page->excerpt }}</p>
        @endif
    </section>

    {{-- Founder message --}}
    <section class="border-t border-line bg-surface">
        <div data-reveal="left" class="mx-auto grid max-w-7xl gap-10 px-6 py-16 lg:grid-cols-[1fr_320px] lg:items-center lg:gap-16 lg:px-8">
            <div>
                <svg viewBox="0 0 32 24" fill="currentColor" class="h-8 w-8 text-accent-soft" aria-hidden="true"><path d="M0 24V14.4C0 6.4 4.8 1.1 12.6 0l1 3.4C9 4.7 6.5 7.6 6.1 12H13v12H0Zm18.8 0V14.4c0-8 4.8-13.3 12.6-14.4l1 3.4c-4.6 1.3-7.1 4.2-7.5 8.6H32v12H18.8Z" /></svg>

                <h2 class="mt-4 text-balance font-display text-2xl font-semibold leading-snug tracking-tight text-ink sm:text-3xl">
                    FynnEdge began with a question: what if getting a loan could feel
                    <span class="text-accent">simpler, smarter, and more human?</span>
                </h2>

                <div class="mt-6 flex flex-col gap-4">
                    @foreach ([
                        'We built FynnEdge to redefine the lending experience — bringing clarity to complexity, technology to convenience, and trust to every financial interaction.',
                        'Our belief is simple: finance should move people forward, not hold them back.',
                        'From that belief came our purpose — simplifying loans, amplifying trust.',
                    ] as $point)
                        <div class="flex items-start gap-3">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                            <p class="text-ink-muted">{{ $point }}</p>
                        </div>
                    @endforeach
                </div>

                <p class="mt-6 font-display text-xl italic text-accent">"Simplifying Loans, Amplifying Trust."</p>

                <div class="mt-8">
                    <p class="font-display text-base font-semibold text-ink">{{ $founderName ?? '[Founder name]' }}</p>
                    <p class="mt-1 text-sm text-ink-faint">Founder, FynnEdge Advisory</p>
                </div>
            </div>

            @if ($founderPhotoUrl)
                <img
                    src="{{ $founderPhotoUrl }}"
                    alt="{{ $founderName ?? 'Founder, FynnEdge Advisory' }}"
                    class="mx-auto aspect-[4/5] w-full max-w-[280px] rounded-2xl border border-line object-cover"
                >
            @else
                <div class="mx-auto flex aspect-[4/5] w-full max-w-[280px] flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-line-strong bg-surface-2 text-center">
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-accent-soft text-accent" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor" class="h-10 w-10"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.4 0-9 2.2-9 5v3h18v-3c0-2.8-4.6-5-9-5Z" /></svg>
                    </span>
                    <p class="px-6 text-xs text-ink-faint">Founder photo coming soon</p>
                </div>
            @endif
        </div>
    </section>

    {{-- Mission & vision --}}
    <section class="border-t border-line bg-surface-2">
        <div class="mx-auto grid max-w-7xl gap-6 px-6 py-16 sm:grid-cols-2 lg:px-8">
            <x-ui.card data-reveal="zoom stagger" class="card-lift transition-colors transition-shadow hover:bg-accent-soft hover:shadow-md hover:animate-card-swing">
                <x-ui.badge tone="accent">Our mission</x-ui.badge>
                <p class="mt-4 text-balance font-display text-xl font-semibold text-ink">Make borrowing simple, transparent and fair.</p>
                <p class="mt-3 text-sm text-ink-muted">
                    We match every applicant with lenders suited to their profile, show clear reasons behind every
                    result, and never leave anyone guessing about what happens next.
                </p>
            </x-ui.card>
            <x-ui.card data-reveal="zoom stagger" class="card-lift transition-colors transition-shadow hover:bg-accent-soft hover:shadow-md hover:animate-card-swing">
                <x-ui.badge tone="pass">Our vision</x-ui.badge>
                <p class="mt-4 text-balance font-display text-xl font-semibold text-ink">A future where comparing credit is as easy as comparing anything else.</p>
                <p class="mt-3 text-sm text-ink-muted">
                    We want every borrower in India to be able to check where they stand, compare their real options,
                    and choose with confidence — instead of applying blind and hoping for the best.
                </p>
            </x-ui.card>
        </div>
    </section>

    {{-- Why FynnEdge --}}
    <section class="border-t border-line bg-surface">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <x-site.why-fynnedge />
        </div>
    </section>

    {{-- Life at FynnEdge --}}
    <section class="border-t border-line bg-surface-2">
        <div data-reveal="right" class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <p class="font-mono text-xs font-semibold uppercase tracking-[0.14em] text-accent">Life at FynnEdge</p>
            <h2 class="mt-3 max-w-xl text-balance font-display text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                A small team, building with real ownership.
            </h2>
            <p class="mt-3 max-w-2xl text-ink-muted">
                We're early-stage and small by design — everyone who joins shapes the product, not just their
                corner of it.
            </p>

            @if ($companyPhotos->isNotEmpty())
                <div class="relative mt-8 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_5%,black_95%,transparent)]">
                    <div class="flex w-max animate-marquee-reverse items-center gap-4 hover:[animation-play-state:paused] motion-reduce:animate-none">
                        @for ($copy = 0; $copy < 2; $copy++)
                            <div class="flex shrink-0 items-center gap-4" @if ($copy === 1) aria-hidden="true" @endif>
                                @foreach ($companyPhotos as $photo)
                                    <img
                                        src="{{ $photo->photoUrl() }}"
                                        alt="{{ $photo->photo_alt ?: ($photo->caption ?: 'Life at FynnEdge') }}"
                                        class="h-48 w-64 shrink-0 rounded-xl object-cover"
                                        loading="lazy"
                                    >
                                @endforeach
                            </div>
                        @endfor
                    </div>
                </div>
            @endif

            <div class="mt-10 grid gap-6 sm:grid-cols-3">
                @foreach ([
                    ['Real ownership', 'Small team, so what you build actually ships — no layers between an idea and the product.'],
                    ['Customer first', 'Every decision starts from what makes the borrower\'s experience clearer and fairer.'],
                    ['Built on trust', 'We\'d rather say "not ready yet" than overstate what we can do — for customers and each other.'],
                ] as $value)
                    <div data-reveal="up stagger">
                        <p class="font-display text-lg font-semibold text-ink">{{ $value[0] }}</p>
                        <p class="mt-2 text-sm text-ink-muted">{{ $value[1] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Work with us --}}
    <section class="border-t border-line bg-accent">
        <div data-reveal="zoom" class="mx-auto max-w-7xl px-6 py-16 text-center lg:px-8">
            <h2 class="mx-auto max-w-xl text-balance font-display text-2xl font-semibold text-white sm:text-3xl">
                Work with us
            </h2>
            <p class="mx-auto mt-3 max-w-lg text-white/80">
                Interested in joining the team? We're a small crew and don't always have open roles listed, but
                we're always happy to hear from people who care about fixing lending.
            </p>
            <x-ui.button tag="a" :href="route('careers')" variant="inverse" class="mt-6">
                See open roles
            </x-ui.button>
        </div>
    </section>
</x-layouts.app>
