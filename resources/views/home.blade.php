<x-layouts.app>
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute -top-32 left-1/2 h-[32rem] w-[32rem] -translate-x-1/2 rounded-full bg-accent/10 blur-3xl"></div>
            <div class="absolute -right-24 top-1/3 h-72 w-72 rounded-full bg-pass/10 blur-3xl"></div>
            <div class="absolute inset-0 [background-image:radial-gradient(var(--color-line-strong)_1px,transparent_1px)] [background-size:28px_28px] [mask-image:radial-gradient(ellipse_60%_60%_at_50%_0%,black,transparent)] opacity-40"></div>
        </div>

        <div class="mx-auto max-w-7xl px-6 pb-20 pt-16 lg:px-8 lg:pt-24">
            <p class="inline-flex items-center gap-2 rounded-full border border-line bg-surface/80 px-3 py-1 font-mono text-xs font-semibold uppercase tracking-[0.14em] text-accent shadow-sm backdrop-blur">
                FynnEdge Advisory (OPC) Pvt Ltd
            </p>
            <h1 class="mt-6 max-w-3xl text-balance font-display text-4xl font-semibold leading-[1.08] tracking-tight text-ink sm:text-5xl lg:text-6xl">
                Simplifying loans.
                <span class="text-accent">Amplifying trust.</span>
            </h1>
            <p class="mt-6 max-w-xl text-lg text-ink-muted">
                FynnEdge connects you with suitable banks and NBFCs for personal loans, home loans, car loans,
                business loans and loans against property — with clear, upfront eligibility before you apply.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <x-ui.button :tag="Route::has('eligibility.index') ? 'a' : 'button'" :href="Route::has('eligibility.index') ? route('eligibility.index') : null" size="lg">
                    Check Your Eligibility
                </x-ui.button>
                <x-ui.button tag="a" :href="route('loans.index')" variant="secondary" size="lg">
                    Explore Loan Products
                </x-ui.button>
            </div>

            @if ($lenders->isNotEmpty())
                <div class="mt-16">
                    <p class="text-center font-mono text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-ink-faint sm:text-left">
                        Trusted by leading banks &amp; NBFCs
                    </p>
                    <div class="relative mt-5 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]">
                        <div class="flex w-max animate-marquee items-center gap-12 hover:[animation-play-state:paused] motion-reduce:animate-none">
                            @for ($copy = 0; $copy < 2; $copy++)
                                <div class="flex shrink-0 items-center gap-12" @if ($copy === 1) aria-hidden="true" @endif>
                                    @foreach ($lenders as $lender)
                                        <div class="flex shrink-0 items-center gap-2.5">
                                            <x-ui.lender-logo :lender="$lender" size="sm" />
                                            <span class="font-display text-sm font-medium text-ink-muted">{{ $lender->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    @if ($loanProducts->isNotEmpty())
        <section class="border-t border-line bg-surface">
            <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
                <h2 class="max-w-xl text-balance font-display text-2xl font-semibold text-ink">
                    What are you looking to finance?
                </h2>
                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($loanProducts as $product)
                        <a href="{{ route('loans.show', $product) }}" class="group">
                            <x-ui.card class="h-full transition-shadow group-hover:shadow-md">
                                <x-ui.badge tone="accent">{{ $product->category->getLabel() }}</x-ui.badge>
                                <p class="mt-3 font-display text-lg font-semibold text-ink group-hover:text-accent">{{ $product->name }}</p>
                                @if ($product->summary)
                                    <p class="mt-2 text-sm text-ink-muted">{{ $product->summary }}</p>
                                @endif
                            </x-ui.card>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="border-t border-line bg-surface-2">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <h2 class="max-w-xl text-balance font-display text-2xl font-semibold text-ink">
                Not sure which loan or lender fits your profile?
            </h2>
            <p class="mt-3 max-w-xl text-ink-muted">
                Tell us about yourself and we'll identify suitable lenders — before you commit to an application.
            </p>
            <x-ui.button class="mt-6" :tag="Route::has('eligibility.index') ? 'a' : 'button'" :href="Route::has('eligibility.index') ? route('eligibility.index') : null">
                Check Your Eligibility
            </x-ui.button>
        </div>
    </section>

    <section class="border-t border-line bg-surface">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <h2 class="max-w-xl text-balance font-display text-2xl font-semibold text-ink">How it works</h2>
            <div class="mt-8 grid gap-10 lg:grid-cols-2">
                <x-ui.stepper :steps="['Tell us about yourself', 'See lenders you\'re likely eligible for', 'Compare and apply', 'Track your application to disbursal']" :current="0" />
                <p class="text-sm text-ink-muted lg:pt-1">
                    One profile, matched against multiple lenders' criteria at once — instead of applying
                    to each bank separately and hoping for the best.
                </p>
            </div>
        </div>
    </section>

    <section class="border-t border-line bg-surface-2">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <h2 class="max-w-xl text-balance font-display text-2xl font-semibold text-ink">Why FynnEdge</h2>
            <div class="mt-8 grid gap-6 sm:grid-cols-3">
                <div>
                    <p class="font-display text-lg font-semibold text-ink">One profile, many lenders</p>
                    <p class="mt-2 text-sm text-ink-muted">Check suitability against multiple banks and NBFCs from a single profile, instead of applying one by one.</p>
                </div>
                <div>
                    <p class="font-display text-lg font-semibold text-ink">Transparent, not opaque</p>
                    <p class="mt-2 text-sm text-ink-muted">We show why a lender is or isn't a fit — not just a yes or no.</p>
                </div>
                <div>
                    <p class="font-display text-lg font-semibold text-ink">Advisory, not just a listing</p>
                    <p class="mt-2 text-sm text-ink-muted">FynnEdge Advisory helps you understand your options, not just aggregate them.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="border-t border-line bg-surface">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="max-w-xl text-balance font-display text-2xl font-semibold text-ink">Plan your EMI</h2>
                <x-ui.button tag="a" :href="route('calculators.index')" variant="secondary" size="sm">Open calculator</x-ui.button>
            </div>
            <p class="mt-3 max-w-xl text-ink-muted">Estimate your monthly instalment before you apply, for any loan type.</p>
        </div>
    </section>

    @if ($faqs->isNotEmpty())
        <section class="border-t border-line bg-surface-2">
            <div class="mx-auto max-w-3xl px-6 py-16 lg:px-8">
                <h2 class="font-display text-2xl font-semibold text-ink">Frequently asked questions</h2>
                <div class="mt-6 flex flex-col divide-y divide-line border-y border-line">
                    @foreach ($faqs as $faq)
                        <details class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-ink">
                                {{ $faq->question }}
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                            </summary>
                            <p class="mt-3 text-sm text-ink-muted">{{ $faq->answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="border-t border-line bg-accent">
        <div class="mx-auto max-w-7xl px-6 py-16 text-center lg:px-8">
            <h2 class="mx-auto max-w-xl text-balance font-display text-2xl font-semibold text-white sm:text-3xl">
                Ready to see what you're eligible for?
            </h2>
            <x-ui.button class="mt-6" variant="inverse" :tag="Route::has('eligibility.index') ? 'a' : 'button'" :href="Route::has('eligibility.index') ? route('eligibility.index') : null">
                Check Your Eligibility
            </x-ui.button>
        </div>
    </section>
</x-layouts.app>
