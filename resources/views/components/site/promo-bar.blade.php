{{--
    The sticky offer pop-up: an edge-to-edge panel that rises from the bottom
    of the page once the visitor scrolls past the admin's limit, and sets again
    when they scroll back above it (delay / exit intent bars stay up once shown
    — see the promoBar Alpine component in resources/js/app.js). It has no
    close button. So it never hides the footer's legal copy, app.css pads
    <body> by --promo-bar-offset.

    The panel spans the full width; its content sits in a centred max-w-7xl
    grid so the text and button don't drift to the far edges of wide screens.
    The grid's named areas give the badge/countdown row the full width on
    phones ("media meta meta" / "media msg cta") while the button spans both
    rows from sm up.

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
        x-transition:enter="transition duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] motion-reduce:duration-0"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-300 ease-in"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        role="region"
        aria-label="Offer"
        style="{{ $promoBar->barStyle() }}"
        class="fixed inset-x-0 bottom-0 z-40"
        data-promo-bar
    >
        {{-- Dawn light breaking over the panel's top edge as it rises. --}}
        <span class="promo-bar-dawn pointer-events-none absolute inset-x-[20%] bottom-full h-12 sm:h-16" aria-hidden="true"></span>

        <div class="promo-bar-surface relative rounded-t-[1.25rem] pb-[env(safe-area-inset-bottom)] sm:rounded-t-[1.75rem]">
            <span class="promo-bar-glow pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]" aria-hidden="true"></span>
            <span class="promo-bar-sunrise pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]" aria-hidden="true"></span>

            <div class="relative mx-auto grid max-w-7xl grid-cols-[auto_minmax(0,1fr)_auto] items-center px-3 py-3 [grid-template-areas:'media_meta_meta'_'media_msg_cta'] sm:px-8 sm:py-4 sm:[grid-template-areas:'media_meta_cta'_'media_msg_cta']">
                @if ($imageUrl)
                    <div class="relative mr-3 w-14 self-stretch [grid-area:media] sm:mr-5 sm:w-28">
                        <img
                            src="{{ $imageUrl }}"
                            alt="{{ $promoBar->image_alt ?? '' }}"
                            class="absolute -bottom-3 left-1/2 h-20 max-w-none -translate-x-1/2 object-contain drop-shadow-xl sm:-bottom-4 sm:h-32"
                        >
                    </div>
                @else
                    <span class="promo-bar-medallion relative mr-5 hidden h-14 w-14 items-center justify-center rounded-full [grid-area:media] sm:flex" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-7 w-7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </span>
                @endif

                @if ($promoBar->eyebrow || $promoBar->countdownEndsAt())
                    <div class="relative mb-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 [grid-area:meta] sm:mb-1">
                        @if ($promoBar->eyebrow)
                            <span class="promo-bar-badge inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[0.625rem] font-bold uppercase tracking-wider sm:text-[0.6875rem]">
                                <span class="relative flex h-1.5 w-1.5" aria-hidden="true">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-current opacity-60 motion-reduce:animate-none"></span>
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-current"></span>
                                </span>
                                {{ $promoBar->eyebrow }}
                            </span>
                        @endif

                        @if ($promoBar->countdownEndsAt())
                            <span x-show="countdown" x-cloak class="inline-flex items-center gap-1" data-promo-bar-countdown>
                                <span class="mr-0.5 hidden text-[0.6875rem] font-medium uppercase tracking-wider opacity-75 sm:inline">Ends in</span>
                                <template x-for="unit in countdownUnits" :key="unit.label">
                                    <span class="promo-bar-tile inline-flex items-baseline rounded-md px-1.5 py-0.5 font-mono text-[0.6875rem] font-semibold tabular-nums sm:text-xs" aria-hidden="true">
                                        <span x-text="unit.value"></span><span class="ml-px text-[0.5625rem] opacity-70" x-text="unit.label"></span>
                                    </span>
                                </template>
                                <span class="sr-only" x-text="'Ends in ' + countdown"></span>
                            </span>
                        @endif
                    </div>
                @endif

                <div class="relative grid [grid-area:msg]" @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
                    @foreach ($messages as $index => $message)
                        <p
                            @if ($index > 0) x-cloak aria-hidden="true" @endif
                            x-show="messageIndex === {{ $index }}"
                            x-transition:enter="transition duration-500 ease-out motion-reduce:transition-none"
                            x-transition:enter-start="translate-y-3 opacity-0"
                            x-transition:enter-end="translate-y-0 opacity-100"
                            class="line-clamp-2 font-display text-[0.875rem] font-semibold leading-snug tracking-tight [grid-area:1/1] sm:text-2xl sm:leading-tight"
                        >{{ $message }}</p>
                    @endforeach
                </div>

                <a
                    href="{{ $promoBar->ctaUrl() }}"
                    @if ($promoBar->cta_opens_new_tab) target="_blank" rel="noopener" @endif
                    @click="track('promo_bar_click')"
                    class="promo-bar-cta group relative ml-3 inline-flex items-center gap-1.5 self-center overflow-hidden rounded-full py-1.5 pl-3.5 pr-1.5 text-[0.8125rem] font-bold whitespace-nowrap transition duration-300 [grid-area:cta] hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white motion-reduce:hover:translate-y-0 sm:ml-5 sm:gap-2 sm:py-2.5 sm:pl-6 sm:pr-2.5 sm:text-base"
                >
                    <span class="relative">{{ $promoBar->cta_label }}</span>
                    <span class="promo-bar-cta-arrow relative flex h-6 w-6 items-center justify-center rounded-full transition duration-300 group-hover:translate-x-0.5 motion-reduce:group-hover:translate-x-0 sm:h-8 sm:w-8" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" class="h-3 w-3 sm:h-4 sm:w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 10h11m-4-5 5 5-5 5" />
                        </svg>
                    </span>
                </a>
            </div>
        </div>
    </div>
@endif
