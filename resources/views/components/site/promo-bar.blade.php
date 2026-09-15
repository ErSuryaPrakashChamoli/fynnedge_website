{{--
    The sticky offer bar: rises from the bottom of the page once the visitor
    scrolls far enough, lingers, or heads for the tab bar (see the promoBar
    Alpine component in resources/js/app.js), and slips away again while the
    footer is on screen so it never covers the legal copy.

    Every message is rendered here, server-side, and only toggled with x-show,
    so the copy stays in the HTML. Colours arrive as custom properties from
    PromoBar::barStyle(); the .promo-bar-* rules in app.css hold the fallbacks.
--}}
@php
    $promoBar = \App\Support\PromoBars\PromoBars::forCurrentPage();
@endphp

@if ($promoBar)
    @php
        $messages = $promoBar->messages();
        $imageUrl = $promoBar->imageUrl();
    @endphp

    <div
        x-data="promoBar(@js($promoBar->clientConfig()))"
        x-show="shown"
        x-cloak
        x-transition:enter="transition duration-500 ease-out"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-300 ease-in"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        role="region"
        aria-label="Offer"
        style="{{ $promoBar->barStyle() }}"
        class="fixed inset-x-0 bottom-0 z-40 px-2 pb-2 sm:px-4 sm:pb-4"
        data-promo-bar
    >
        <div class="promo-bar-surface relative mx-auto flex max-w-5xl items-center gap-3 rounded-2xl py-2.5 pl-3 pr-3 shadow-2xl sm:gap-5 sm:rounded-3xl sm:py-3 sm:pl-6 sm:pr-5">
            <span class="promo-bar-shine pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]" aria-hidden="true"></span>

            @if ($imageUrl)
                <div class="relative w-14 shrink-0 self-stretch sm:w-28">
                    <img
                        src="{{ $imageUrl }}"
                        alt="{{ $promoBar->image_alt ?? '' }}"
                        class="absolute -bottom-2.5 left-1/2 h-20 max-w-none -translate-x-1/2 object-contain drop-shadow-xl sm:-bottom-3 sm:h-32"
                    >
                </div>
            @endif

            <div class="relative min-w-0 flex-1">
                @if ($promoBar->eyebrow || $promoBar->countdownEndsAt())
                    <div class="mb-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-1">
                        @if ($promoBar->eyebrow)
                            <span class="promo-bar-badge inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[0.625rem] font-semibold uppercase tracking-wider sm:text-[0.6875rem]">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current motion-reduce:animate-none" aria-hidden="true"></span>
                                {{ $promoBar->eyebrow }}
                            </span>
                        @endif

                        @if ($promoBar->countdownEndsAt())
                            <span x-show="countdown" x-cloak class="font-mono text-[0.6875rem] tabular-nums opacity-90 sm:text-xs" data-promo-bar-countdown>
                                Ends in <span x-text="countdown"></span>
                            </span>
                        @endif
                    </div>
                @endif

                <div class="grid" @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
                    @foreach ($messages as $index => $message)
                        <p
                            @if ($index > 0) x-cloak aria-hidden="true" @endif
                            x-show="messageIndex === {{ $index }}"
                            x-transition:enter="transition duration-500 ease-out motion-reduce:transition-none"
                            x-transition:enter-start="translate-y-2 opacity-0"
                            x-transition:enter-end="translate-y-0 opacity-100"
                            class="line-clamp-2 font-display text-sm leading-snug [grid-area:1/1] sm:text-2xl sm:leading-tight"
                        >{{ $message }}</p>
                    @endforeach
                </div>
            </div>

            <a
                href="{{ $promoBar->ctaUrl() }}"
                @if ($promoBar->cta_opens_new_tab) target="_blank" rel="noopener" @endif
                @click="track('promo_bar_click')"
                class="promo-bar-cta group relative inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3.5 py-2 text-sm font-semibold shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white motion-reduce:hover:translate-y-0 sm:px-6 sm:py-3 sm:text-base"
            >
                {{ $promoBar->cta_label }}
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 transition group-hover:translate-x-1 motion-reduce:group-hover:translate-x-0" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 10h11m-4-5 5 5-5 5" />
                </svg>
            </a>

            <button
                type="button"
                @click="dismiss()"
                aria-label="Close offer"
                class="absolute -top-2.5 right-3 flex h-7 w-7 items-center justify-center rounded-full bg-surface text-ink shadow-md ring-1 ring-line transition hover:bg-surface-2"
            >
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" d="M5 5l10 10M15 5 5 15" /></svg>
            </button>
        </div>
    </div>
@endif
