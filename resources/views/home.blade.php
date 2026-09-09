<x-layouts.app handles-faqs>
    <x-site.flexi-hybrid-ticker :loan-product="$flexiHybridProduct" :marketing="$flexiHybridTicker" />

    {{--
        Hero banner: a hardcoded-height 40/60 split.

        Sizing is deliberately fixed rather than fluid so that the header, the
        Flexi Hybrid ticker, this banner and the lender marquee beneath it all
        land inside a desktop viewport with no scrolling. The left column is
        static copy (Settings-driven); the right column is the admin-managed
        sliding banner "crawler". Both columns share the same hardcoded
        heights — change them in BOTH this file and
        components/site/banner-carousel.blade.php or the row will jump.

        `lg:grid-cols-[2fr_3fr]` is the 40/60 split: fr shares are computed
        after the gap is subtracted, so the ratio holds exactly at any width,
        which literal 40%/60% columns would not once a gap is added.

        With no banner published the left column simply spans the full width —
        an empty promotional slot must never render as an empty grey box.
    --}}
    <section class="relative overflow-hidden border-b border-line">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute -top-32 left-1/2 h-[32rem] w-[32rem] -translate-x-1/2 rounded-full bg-accent/10 blur-3xl"></div>
            <div class="absolute -right-24 top-1/3 h-72 w-72 rounded-full bg-pass/10 blur-3xl"></div>
            <div class="absolute inset-0 [background-image:radial-gradient(var(--color-line-strong)_1px,transparent_1px)] [background-size:28px_28px] [mask-image:radial-gradient(ellipse_60%_60%_at_50%_0%,black,transparent)] opacity-40"></div>
        </div>

        <div class="mx-auto max-w-7xl px-6 py-5 lg:px-8 lg:py-6">
            <div @class([
                'grid items-center gap-8 lg:gap-10',
                'lg:grid-cols-[2fr_3fr]' => $banners->isNotEmpty(),
            ])>
                {{-- Left 40% — static, never animated. --}}
                <div class="flex flex-col justify-center">
                    @if ($hero['eyebrow'])
                        <p data-reveal="up" class="inline-flex w-max items-center gap-2 rounded-full border border-line bg-surface/80 px-3 py-1 font-mono text-xs font-semibold uppercase tracking-[0.14em] text-accent shadow-sm backdrop-blur">
                            {{ $hero['eyebrow'] }}
                        </p>
                    @endif
                    <h1 data-reveal="up delay-1" class="mt-4 text-balance font-display text-3xl font-semibold leading-[1.03] tracking-tight text-ink sm:text-4xl lg:text-[2.5rem] xl:text-5xl">
                        {{ $hero['heading'] }}
                        <span class="text-accent">{{ $hero['headingAccent'] }}</span>
                    </h1>
                    <p data-reveal="up delay-2" class="mt-4 max-w-xl text-sm text-ink-muted lg:text-base">
                        {{ $hero['subheading'] }}
                    </p>

                    <div data-reveal="up delay-2" class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button :tag="Route::has('eligibility.index') ? 'a' : 'button'" :href="Route::has('eligibility.index') ? route('eligibility.index') : null" size="lg">
                            Check Your Eligibility
                        </x-ui.button>
                        <x-ui.button tag="a" :href="route('loans.index')" variant="secondary" size="lg">
                            Explore Loan Products
                        </x-ui.button>
                    </div>
                </div>

                {{-- Right 60% — admin-managed sliding promotional banners. --}}
                @if ($banners->isNotEmpty())
                    <div data-reveal="zoom delay-3">
                        <x-site.banner-carousel :banners="$banners" />
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{--
        Lender marquee, pulled directly beneath the banner so it is visible
        without scrolling rather than sitting further down the hero.
    --}}
    @if ($lenders->isNotEmpty())
        <section class="border-b border-line bg-surface">
            <div data-reveal="fade" class="mx-auto max-w-7xl px-6 py-4 lg:px-8">
                <p class="text-center font-mono text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-ink-faint sm:text-left">
                    Trusted by leading banks &amp; NBFCs
                </p>
                <div class="relative mt-3 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]">
                    <div class="flex w-max animate-marquee items-center gap-12 hover:[animation-play-state:paused] motion-reduce:animate-none">
                        @for ($copy = 0; $copy < 2; $copy++)
                            <div class="flex shrink-0 items-center gap-12" @if ($copy === 1) aria-hidden="true" @endif>
                                @foreach ($lenders as $lender)
                                    <div class="flex shrink-0 items-center gap-2.5">
                                        <x-ui.lender-logo :lender="$lender" size="sm" />
                                        <span class="font-display text-sm font-semibold text-ink-muted">{{ $lender->name }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </section>
    @endif

    <x-site.hero-stats :achievements="$achievements" :derived="$heroStats" />

    @if ($loanProducts->isNotEmpty())
        <section class="border-t border-line bg-surface">
            <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
                <h2 data-reveal="up" class="max-w-xl text-balance font-display text-2xl font-semibold text-ink">
                    What are you looking to finance?
                </h2>
                <p data-reveal="up" class="mt-2 max-w-xl text-ink-muted">
                    Pick a product to see what's involved — or browse the full list.
                </p>

                <div x-data="{ active: '{{ $loanProducts->first()->slug }}' }" class="mt-8">
                    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Loan products">
                        @foreach ($loanProducts as $product)
                            <button
                                type="button"
                                role="tab"
                                data-reveal="left stagger"
                                :aria-selected="active === '{{ $product->slug }}'"
                                @click="active = '{{ $product->slug }}'"
                                :class="active === '{{ $product->slug }}' ? 'border-accent bg-accent text-white' : 'border-line text-ink-muted hover:border-line-strong hover:text-ink'"
                                class="cursor-pointer rounded-full border px-4 py-2 text-sm font-medium transition-colors"
                            >
                                {{ $product->name }}
                            </button>
                        @endforeach
                    </div>

                    <div data-reveal="zoom" class="mt-6 rounded-2xl border border-line bg-surface-2 p-6 sm:p-8">
                        @foreach ($loanProducts as $product)
                            <div x-show="active === '{{ $product->slug }}'" x-cloak>
                                <div class="flex flex-wrap items-start justify-between gap-6">
                                    <div>
                                        <x-ui.badge tone="accent">{{ $product->category->getLabel() }}</x-ui.badge>
                                        <p class="mt-3 font-display text-2xl font-semibold text-ink">{{ $product->name }}</p>
                                        @if ($product->summary)
                                            <p class="mt-2 max-w-xl text-ink-muted">{{ $product->summary }}</p>
                                        @endif
                                    </div>
                                    <x-ui.button tag="a" :href="route('loans.apply', $product)" size="sm" class="shrink-0">
                                        Check Eligibility
                                    </x-ui.button>
                                </div>

                                @if (! empty($product->benefits))
                                    <ul class="mt-6 grid gap-2.5 sm:grid-cols-2">
                                        @foreach (array_slice($product->benefits, 0, 4) as $benefit)
                                            <li class="flex items-start gap-2.5 text-sm text-ink-muted">
                                                <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-accent" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                                                {{ $benefit }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                <a href="{{ route('loans.show', $product) }}" class="mt-6 inline-flex items-center gap-1 text-sm font-medium text-accent hover:underline">
                                    View full details
                                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="border-t border-line bg-surface-2">
        <div data-reveal="right" class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <h2 class="max-w-xl text-balance font-display text-2xl font-semibold text-ink">
                {{ $financeCta->heading ?? 'Not sure which loan or lender fits your profile?' }}
            </h2>
            <p class="mt-3 max-w-xl text-ink-muted">
                {{ $financeCta->description ?? "Tell us about yourself and we'll identify suitable lenders — before you commit to an application." }}
            </p>
            @if ($financeCta?->cta_url)
                <x-ui.button class="mt-6" tag="a" :href="$financeCta->cta_url">
                    {{ $financeCta->cta_label ?: 'Check Your Eligibility' }}
                </x-ui.button>
            @else
                <x-ui.button class="mt-6" :tag="Route::has('eligibility.index') ? 'a' : 'button'" :href="Route::has('eligibility.index') ? route('eligibility.index') : null">
                    Check Your Eligibility
                </x-ui.button>
            @endif
        </div>
    </section>

    <section class="border-t border-line bg-surface">
        <div data-reveal="left" class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <h2 class="max-w-xl text-balance font-display text-2xl font-semibold text-ink">How it works</h2>
            <div class="mt-8 grid gap-10 lg:grid-cols-2">
                @if ($howItWorksSteps->isNotEmpty())
                    <x-site.how-it-works :steps="$howItWorksSteps" />
                @else
                    <x-ui.stepper :steps="['Tell us about yourself', 'See lenders you\'re likely eligible for', 'Compare and apply', 'Track your application to disbursal']" :current="0" />
                @endif
                <p class="text-sm text-ink-muted lg:pt-1">
                    One profile, matched against multiple lenders' criteria at once — instead of applying
                    to each bank separately and hoping for the best.
                </p>
            </div>
        </div>
    </section>

    <section class="border-t border-line bg-surface-2">
        <div class="mx-auto grid max-w-7xl gap-12 px-6 py-16 lg:grid-cols-[1fr_360px] lg:items-center lg:gap-16 lg:px-8">
            <div data-reveal="left">
                <x-ui.badge tone="accent">Free score check</x-ui.badge>
                <h2 class="mt-4 max-w-lg text-balance font-display text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                    Know your credit score before you apply
                </h2>
                <p class="mt-3 max-w-lg text-ink-muted">
                    A quick, mobile-OTP-verified check shows where you stand with major bureaus — free, and it
                    never affects your real credit history.
                </p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-ui.button tag="a" :href="route('credit-score.show', ['bureau' => 'cibil'])" variant="secondary" size="sm">CIBIL</x-ui.button>
                    <x-ui.button tag="a" :href="route('credit-score.show', ['bureau' => 'experian'])" variant="secondary" size="sm">Experian</x-ui.button>
                    <x-ui.button tag="a" :href="route('credit-score.show', ['bureau' => 'equifax'])" variant="secondary" size="sm">Equifax</x-ui.button>
                    <x-ui.button tag="a" :href="route('credit-score.show', ['bureau' => 'crif'])" variant="secondary" size="sm">CRIF</x-ui.button>
                </div>
            </div>

            {{--
                The needle's rotation and the score text are both derived from the same
                `progress` value on every animation frame, so they can never drift out of
                sync with each other — the score rises exactly as the needle rises, and
                falls exactly as it falls. Using two independent CSS/JS animations for
                these (one for rotation, one for a number tween) would not guarantee that.
            --}}
            <x-ui.card
                x-data="{
                    min: 300,
                    max: 900,
                    angleMin: -80,
                    angleMax: 80,
                    angle: -80,
                    score: 300,
                    frame: null,
                    tick(timestamp) {
                        // A sawtooth, not a back-and-forth oscillation: score
                        // rises from 300 to 900, holds briefly, then resets
                        // straight back to 300 and rises again — a repeating
                        // count-up rather than a needle swinging both ways.
                        const riseDuration = 3000;
                        const holdDuration = 500;
                        const period = riseDuration + holdDuration;
                        const cyclePosition = timestamp % period;
                        const linearProgress = Math.min(cyclePosition / riseDuration, 1);
                        const progress = 1 - ((1 - linearProgress) ** 2);
                        this.angle = this.angleMin + (progress * (this.angleMax - this.angleMin));
                        this.score = Math.round(this.min + (progress * (this.max - this.min)));
                        this.frame = requestAnimationFrame((t) => this.tick(t));
                    },
                    start() {
                        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                            this.angle = 20;
                            this.score = 760;

                            return;
                        }

                        this.frame = requestAnimationFrame((t) => this.tick(t));
                    },
                }"
                x-init="start()"
                data-reveal="right"
                class="card-lift text-center"
            >
                <svg viewBox="0 0 200 128" class="mx-auto w-full max-w-[260px]" aria-hidden="true">
                    <defs>
                        <linearGradient id="scoreGaugeGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color: var(--color-warn)" />
                            <stop offset="50%" style="stop-color: var(--color-warn-soft)" />
                            <stop offset="100%" style="stop-color: var(--color-pass)" />
                        </linearGradient>
                    </defs>

                    <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="var(--color-line)" stroke-width="18" stroke-linecap="round" />
                    <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="url(#scoreGaugeGradient)" stroke-width="13" stroke-linecap="round" />

                    <text x="20" y="118" text-anchor="middle" class="font-mono" style="fill: var(--color-ink-faint); font-size: 9px;">300</text>
                    <text x="180" y="118" text-anchor="middle" class="font-mono" style="fill: var(--color-ink-faint); font-size: 9px;">900</text>

                    <g :style="`transform: rotate(${angle}deg); transform-box: view-box; transform-origin: 100px 100px;`">
                        <polygon points="96,100 100,26 104,100" style="fill: var(--color-ink)" />
                        <polygon points="97,100 100,110 103,100" style="fill: var(--color-ink-faint)" />
                    </g>
                    <circle cx="100" cy="100" r="8.5" style="fill: var(--color-accent)" />
                    <circle cx="100" cy="100" r="3.5" style="fill: var(--color-surface)" />
                </svg>
                <p class="-mt-1 font-display text-3xl font-semibold text-ink" x-text="score">760</p>
                <p class="mt-1 font-mono text-[0.65rem] uppercase tracking-wider text-ink-faint">Score — check yours free</p>
                <p class="mt-3 whitespace-nowrap font-display text-[0.72rem] font-semibold tracking-tight text-accent">Know Your Credit. Unlock Your Possibilities.</p>
            </x-ui.card>
        </div>
    </section>

    <section class="border-t border-line bg-surface">
        <div data-reveal="down" class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <h2 class="max-w-xl text-balance font-display text-2xl font-semibold text-ink">{{ $emiCta->heading ?? 'Plan your EMI' }}</h2>
                <x-ui.button tag="a" :href="$emiCta->cta_url ?? route('calculators.index')" variant="secondary" size="sm">
                    {{ $emiCta->cta_label ?? 'Open calculator' }}
                </x-ui.button>
            </div>
            <p class="mt-3 max-w-xl text-ink-muted">{{ $emiCta->description ?? 'Estimate your monthly instalment before you apply, for any loan type.' }}</p>
        </div>
    </section>

    <section class="border-t border-line bg-accent">
        <div data-reveal="zoom" class="mx-auto max-w-7xl px-6 py-16 text-center lg:px-8">
            <h2 class="mx-auto max-w-xl text-balance font-display text-2xl font-semibold text-white sm:text-3xl">
                {{ $finalCta->heading ?? "Ready to see what you're eligible for?" }}
            </h2>
            @if ($finalCta?->cta_url)
                <x-ui.button class="mt-6" variant="inverse" tag="a" :href="$finalCta->cta_url">
                    {{ $finalCta->cta_label ?: 'Check Your Eligibility' }}
                </x-ui.button>
            @else
                <x-ui.button class="mt-6" variant="inverse" :tag="Route::has('eligibility.index') ? 'a' : 'button'" :href="Route::has('eligibility.index') ? route('eligibility.index') : null">
                    Check Your Eligibility
                </x-ui.button>
            @endif
        </div>
    </section>

    <section class="border-t border-line bg-surface">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <x-site.testimonials :testimonials="$testimonials" />
            <x-site.faq-accordion :faqs="$faqs" data-ai-context="Frequently Asked Questions" />
            <x-site.faq-json-ld :faqs="$faqs" />
        </div>
    </section>
</x-layouts.app>
