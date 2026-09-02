@props(['loanProduct' => null, 'marketing' => null])

{{--
    Homepage-only announcement strip for Flexi Hybrid Term Loan — a specialty
    product FynnEdge wants highlighted, not just listed alongside every other
    loan type. Must be the very first element inside home.blade.php's own
    markup (before the hero section) so it lands immediately below
    <x-site.header> in real DOM order, same "no CSS hacks" positioning rule
    as the Flexi Hybrid product page's own hero.

    `.flexi-hybrid-panel` (resources/css/app.css) scopes the same deep
    slate-navy brand palette used on that product page's hero, so the two
    surfaces read as one consistent, deliberately-highlighted treatment
    instead of another plain content block — this is the "we have expertise
    in it" emphasis.

    The scrolling text reuses the same --animate-marquee keyframe
    (translateX(0) -> -50%) as the homepage's existing lender-logo strip
    further down the page — content flows right to left, and is duplicated
    (aria-hidden on the second copy) for a seamless loop.

    Badge label / scrolling text / button label+link are admin-editable via
    the MarketingSection 'home_flexi_hybrid_ticker' placement (additive, same
    fallback-to-hardcoded convention as the other home.blade.php placements
    — see .ai/rules/components-site.md). The strip's visibility itself stays
    tied to $loanProduct, not to the marketing row, so publishing copy here
    can never resurrect the strip once the product is unpublished.
--}}
{{--
    Only renders once a Flexi Hybrid Term Loan product is actually published
    — a promotional strip advertising the product with nowhere real for
    "Apply Now" to go would be a dead end, the same principle LoanMegaMenu
    already applies to the header's "Loans" menu.
--}}
@if ($loanProduct)
    @php
        $tickerText = $marketing?->description;
        $ctaLabel = $marketing?->cta_label ?: 'Apply Now';
        $ctaUrl = $marketing?->cta_url ?: (Route::has('loans.apply') ? route('loans.apply', $loanProduct) : null);
    @endphp
    <div class="flexi-hybrid-panel relative overflow-hidden border-b border-line bg-surface">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2.5 sm:gap-5 sm:px-6 lg:px-8">
            <span class="hidden shrink-0 items-center gap-1.5 rounded-full bg-accent-soft px-3 py-1 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-accent sm:inline-flex">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3" aria-hidden="true"><path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.2 1 5.9L10 15l-5.2 2.8 1-5.9L1.5 7.7l5.9-.8L10 1.5Z" /></svg>
                {{ $marketing?->heading ?: 'Our Specialty' }}
            </span>

            <div class="relative min-w-0 flex-1 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_4%,black_96%,transparent)]">
                <div class="flex w-max animate-marquee items-center gap-10 whitespace-nowrap hover:[animation-play-state:paused] motion-reduce:animate-none">
                    @for ($copy = 0; $copy < 2; $copy++)
                        <div class="flex shrink-0 items-center gap-10" @if ($copy === 1) aria-hidden="true" @endif>
                            @for ($repeat = 0; $repeat < 4; $repeat++)
                                <span class="flex items-center gap-2 font-display text-sm font-semibold text-ink sm:text-base">
                                    @if ($tickerText)
                                        {{ $tickerText }}
                                    @else
                                        Flexi Hybrid Term Loan <span class="text-ink-faint">(Overdraft)</span>
                                        <span class="text-accent">—</span>
                                        Bajaj Finance <span class="text-ink-faint">//</span> Tata Capital <span class="text-ink-faint">//</span> Piramal Finance <span class="text-ink-faint">//</span> Kotak Mahindra Bank
                                    @endif
                                </span>
                                <span class="text-accent" aria-hidden="true">&#9733;</span>
                            @endfor
                        </div>
                    @endfor
                </div>
            </div>

            @if ($ctaUrl)
                <x-ui.button tag="a" :href="$ctaUrl" size="sm" class="shrink-0">
                    {{ $ctaLabel }}
                </x-ui.button>
            @endif
        </div>
    </div>
@endif
