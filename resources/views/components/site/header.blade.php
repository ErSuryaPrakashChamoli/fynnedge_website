{{--
    The top strip sits ABOVE the header, so it is the first thing a visitor
    meets: Debt Consolidation (while live), the language switch, then the
    customer portal's Sign in pill in the page's top-right corner. The
    1024–1279px header row has no spare width for another control (see
    .ai/rules/site.md).

    Strip and header stick to the top together ([data-site-top]): the user
    asked (2026-11-26) for the Debt Consolidation band to stay in view while
    scrolling, where it used to scroll away above the sticky header. Their
    combined height is published as --site-header-height on <html> (measured
    on every resize, so a wrapped phone strip or a longer Hindi line is
    counted), and every sticky panel and scroll target below the header
    offsets itself by it instead of a fixed top-24.
--}}
@php
    $debtConsolidationLive = \App\Support\Pages\DebtConsolidationPages::hubIsLive();
    $debtAssessmentUrl = $debtConsolidationLive ? \App\Support\Pages\DebtConsolidationPages::liveUrl('assessment') : null;
@endphp

<div
    class="sticky top-0 z-40"
    x-data
    x-init="new ResizeObserver(() => document.documentElement.style.setProperty('--site-header-height', `${$el.offsetHeight}px`)).observe($el)"
    data-site-top
>
<div @class(['border-b', 'dc-strip' => $debtConsolidationLive, 'border-line bg-surface' => ! $debtConsolidationLive]) data-language-strip>
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-end gap-x-2 gap-y-1.5 px-6 py-1.5 sm:flex-nowrap sm:gap-3 lg:px-8">
        {{--
            Debt Consolidation is the service the user asked (2026-11-25) to be
            preferred across the site. The header row stays as it was (the user
            asked on 2026-11-26 for it to leave the nav), so this strip is its
            highlighted home instead: while it is live the whole strip turns
            into a champagne band (.dc-strip) that opens with it, on the left
            from sm and as its own line above the language switch on phones.
            Everything else in the strip is shrink-0 so only the tagline gives
            way (with an ellipsis) when space runs short; the icon waits for md
            and the Free assessment button for lg, where there is room.
        --}}
        @if ($debtConsolidationLive)
            <div class="flex min-w-0 basis-full items-center gap-3 sm:mr-auto sm:basis-auto" data-debt-consolidation-strip>
                <a href="{{ route('debt-consolidation.hub') }}" class="group inline-flex min-w-0 items-center gap-2 text-xs">
                    <span class="dc-strip-icon hidden h-6 w-6 shrink-0 items-center justify-center rounded-full md:flex" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h2.5l4 5H17M3 15h2.5l4-5M3 10h6.5m4.5-3 3 3-3 3" /></svg>
                    </span>
                    <span class="nav-champagne-badge shrink-0 rounded-full px-1.5 font-mono text-[0.6rem] font-semibold uppercase leading-4 tracking-wider">{{ __('New') }}</span>
                    <span class="shrink-0 font-semibold text-ink transition-colors group-hover:text-accent">{{ __('Debt Consolidation') }}</span>
                    <span class="hidden min-w-0 items-center gap-2 text-ink-muted xl:inline-flex">
                        <span class="h-1 w-1 shrink-0 rounded-full bg-champagne" aria-hidden="true"></span>
                        <span class="min-w-0 truncate">{{ __('Many EMIs? See if one loan would really cost less.') }}</span>
                    </span>
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" @class(['h-3 w-3 shrink-0 text-accent transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none', 'lg:hidden' => $debtAssessmentUrl]) aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8 5 5 5-5 5" /></svg>
                </a>
                @if ($debtAssessmentUrl)
                    <a href="{{ $debtAssessmentUrl }}" class="dc-strip-cta group hidden shrink-0 items-center gap-1 rounded-full px-2.5 text-xs font-semibold leading-6 whitespace-nowrap focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent lg:inline-flex" data-debt-consolidation-strip-cta>
                        {{ __('Free assessment') }}
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8 5 5 5-5 5" /></svg>
                    </a>
                @endif
            </div>
        @endif
        <span class="hidden shrink-0 items-center gap-1.5 whitespace-nowrap text-xs text-ink-faint sm:inline-flex" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="h-3.5 w-3.5"><circle cx="10" cy="10" r="7.25" /><path stroke-linecap="round" d="M2.75 10h14.5M10 2.75c1.9 2 2.9 4.4 2.9 7.25S11.9 15.25 10 17.25C8.1 15.25 7.1 12.85 7.1 10S8.1 4.75 10 2.75Z" /></svg>
            Language · <span lang="hi">भाषा</span>
        </span>
        <x-site.language-switch />
        <span class="h-4 w-px shrink-0 bg-line-strong" aria-hidden="true"></span>
        @auth('customer')
            <a href="{{ route('portal.dashboard') }}" class="portal-pill group inline-flex shrink-0 items-center gap-1.5 rounded-full p-[3px] pr-3 text-xs font-semibold leading-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" data-portal-link>
                <span class="portal-pill-avatar relative flex h-6 w-6 items-center justify-center rounded-full">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path d="M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0H3Z" /></svg>
                    <span class="portal-pill-status absolute -bottom-px -right-px h-2 w-2 rounded-full" aria-hidden="true"></span>
                </span>
                {{ __('My Profile') }}
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8 5 5 5-5 5" /></svg>
            </a>
        @else
            <a href="{{ route('customer.login') }}" class="portal-pill group inline-flex shrink-0 items-center gap-1.5 rounded-full p-[3px] pr-3 text-xs font-semibold leading-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" data-portal-link>
                <span class="portal-pill-avatar flex h-6 w-6 items-center justify-center rounded-full">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path d="M10 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0H3Z" /></svg>
                </span>
                {{ __('Sign in') }}
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="h-3 w-3 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8 5 5 5-5 5" /></svg>
            </a>
        @endauth
    </div>
</div>

<header class="relative border-b border-line bg-bg/85 backdrop-blur">
    {{--
        The mobile menu is taller than a phone screen once a section is
        expanded, and it hangs off a sticky header — so without a height of its
        own, its lower links sat below the screen and every scroll moved the
        page behind it instead. While open, the panel is capped at the height
        left under the header (re-measured on resize/rotation), scrolls by
        itself with overscroll contained, and the page underneath is locked.
    --}}
    <div
        x-data="{
            open: false,
            menuHeight: null,
            toggle() {
                this.open = ! this.open;
                if (this.open) {
                    this.fit();
                }
            },
            fit() {
                this.menuHeight = Math.max(0, Math.floor(window.innerHeight - this.$root.closest('header').getBoundingClientRect().bottom));
            },
        }"
        x-effect="document.documentElement.classList.toggle('overflow-hidden', open)"
        @resize.window="if (window.innerWidth >= 1024) { open = false } else if (open) { fit() }"
        @keydown.escape.window="open = false"
        @click.outside="open = false"
        class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4 lg:px-8"
    >
        <a href="{{ route('home') }}" class="flex flex-col text-ink">
            <span class="flex items-center gap-2 font-display text-xl font-semibold tracking-tight">
                <picture>
                    @if ($siteBranding['logoSrcset'])
                        <source type="image/webp" srcset="{{ $siteBranding['logoSrcset'] }}" sizes="36px">
                    @endif
                    <img src="{{ $siteBranding['logoUrl'] }}" alt="" class="h-9 w-9" width="36" height="36">
                </picture>
                <span class="flex items-baseline gap-2">
                    {{ $siteBranding['name'] }}
                    <span class="hidden font-mono text-[0.62rem] font-medium uppercase tracking-[0.14em] text-ink-faint sm:inline">Advisory</span>
                </span>
            </span>
            <span class="mt-0.5 font-display text-[0.65rem] italic text-accent">{{ $siteBranding['tagline'] }}</span>
        </a>

        @php $loanCategories = \App\Support\Loans\LoanMegaMenu::categories(); @endphp

        <nav class="hidden items-center gap-3.5 lg:flex xl:gap-6" aria-label="{{ __('Primary') }}">
            @if (count($loanCategories))
                <div x-data="{ open: false, active: '{{ $loanCategories[0]['overviewParams']['loanProduct'] }}' }" @click.outside="open = false" @keydown.escape="open = false" @mouseenter="open = true" @mouseleave="open = false" class="relative">
                    <button
                        type="button"
                        @click="open = !open"
                        :aria-expanded="open"
                        @if (request()->routeIs('loans.*')) aria-current="true" @endif
                        @class([
                            'flex cursor-pointer items-center gap-1 text-sm font-medium transition-colors',
                            'text-accent font-semibold' => request()->routeIs('loans.*'),
                            'text-ink-muted hover:text-ink' => ! request()->routeIs('loans.*'),
                        ])
                    >
                        {{ __('Loans') }}
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': open }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        @click="open = false"
                        class="absolute -left-48 top-full z-10 w-max pt-3"
                    >
                        {{--
                            -left-48 centres the panel under the max-w-7xl header from xl up:
                            anchored at the Loans button (left-0), the fourth "By Location"
                            column ran past the right edge of a 1366–1450px screen. Below xl
                            that column wraps onto a second row instead of widening the panel.
                        --}}
                        {!! \App\Support\Loans\LoanMegaMenu::panelHtml() !!}
                    </div>
                </div>
            @else
                <x-site.nav-link route="loans.index" :label="__('Loans')" />
            @endif
            <x-site.nav-dropdown :label="__('Credit Score')" activeWhen="credit-score.*" featured :badge="__('Free')">
                <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'cibil']" :label="__('Free :bureau Score', ['bureau' => 'CIBIL'])" class="block whitespace-nowrap rounded-lg px-3 py-1 hover:bg-surface-2" />
                <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'experian']" :label="__('Free :bureau Score', ['bureau' => 'Experian'])" class="block whitespace-nowrap rounded-lg px-3 py-1 hover:bg-surface-2" />
                <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'equifax']" :label="__('Free :bureau Score', ['bureau' => 'Equifax'])" class="block whitespace-nowrap rounded-lg px-3 py-1 hover:bg-surface-2" />
                <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'crif']" :label="__('Free :bureau Score', ['bureau' => 'CRIF'])" class="block whitespace-nowrap rounded-lg px-3 py-1 hover:bg-surface-2" />
            </x-site.nav-dropdown>
            {{--
                The three-column Calculators panel (~790px) is wider than the room
                right of its button at every desktop width, so anchored at the
                button it ran past the screen. On open it is shifted left just
                enough to end at the header row's right content edge (under
                Check Eligibility), never past its left edge. Measured, not a fixed
                offset like the Loans panel's -left-48, because the button moves
                with the locale.
            --}}
            <div
                x-data="{
                    open: false,
                    offset: 0,
                    place() {
                        const row = this.$root.closest('nav').parentElement;
                        const rowBox = row.getBoundingClientRect();
                        const rowStyle = getComputedStyle(row);
                        const start = this.$root.getBoundingClientRect().left;
                        const end = start + this.$refs.panel.offsetWidth;
                        const rowLeft = rowBox.left + parseFloat(rowStyle.paddingLeft);
                        const rowRight = rowBox.right - parseFloat(rowStyle.paddingRight);
                        this.offset = Math.max(rowLeft - start, Math.min(0, rowRight - end));
                    },
                }"
                x-init="$watch('open', (isOpen) => isOpen && $nextTick(() => place()))"
                @resize.window="open && place()"
                @click.outside="open = false"
                @keydown.escape="open = false"
                @mouseenter="open = true"
                @mouseleave="open = false"
                class="relative"
                data-calculators-menu
            >
                <button
                    type="button"
                    @click="open = !open"
                    :aria-expanded="open"
                    @if (request()->routeIs('calculators.*')) aria-current="true" @endif
                    @class([
                        'flex cursor-pointer items-center gap-1 text-sm font-medium transition-colors',
                        'text-accent font-semibold' => request()->routeIs('calculators.*'),
                        'text-ink-muted hover:text-ink' => ! request()->routeIs('calculators.*'),
                    ])
                >
                    {{ __('Calculators') }}
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': open }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
                </button>

                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    @click="open = false"
                    x-ref="panel"
                    :style="{ left: offset + 'px' }"
                    class="absolute left-0 top-full z-10 w-max pt-3"
                >
                    <div class="nav-dropdown-panel rounded-xl border border-line bg-surface p-5 shadow-lg">
                        <div class="grid grid-cols-[repeat(3,max-content)] divide-x divide-line">
                            @foreach (\App\Support\Calculators\CalculatorCatalog::groups() as $groupLabel => $items)
                                <div class="px-4 first:pl-0 last:pr-0">
                                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ $groupLabel }}</p>
                                    <div class="mt-2 flex flex-col">
                                        @foreach ($items as $item)
                                            <x-site.nav-link :route="$item['route']" :parameters="$item['params']" :label="$item['label']" class="block whitespace-nowrap rounded-lg px-3 py-1 text-sm leading-snug hover:bg-surface-2" />
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 border-t border-line pt-4">
                            <x-site.nav-link route="calculators.index" :label="__('View all calculators →')" class="block px-3 text-sm font-medium !text-accent hover:underline" />
                        </div>
                    </div>
                </div>
            </div>

            {{--
                nowrap: a two-word Hindi label (हमारे बारे में) otherwise breaks onto two lines at 1024px.
                No Eligibility link: the user asked (2026-11-26) to drop it, because the highlighted
                Check Eligibility button beside the menu opens the same page.
            --}}
            <x-site.nav-link route="resources.index" :label="__('Resources')" class="whitespace-nowrap" />
            <x-site.nav-link route="faqs.index" :label="__('FAQs')" class="whitespace-nowrap" />
            <x-site.nav-link route="about" :label="__('About')" class="whitespace-nowrap" />
        </nav>

        {{--
            The buttons never wrap: a wrapped label made each button a tall, lopsided pill. Below xl
            only Check Eligibility fits (Talk to us stays in the mobile menu and the footer). The
            transparent border matches the outlined button's height so the two line up.
        --}}
        <div class="hidden shrink-0 items-center gap-3 lg:flex" data-header-cta>
            <x-ui.button tag="a" :href="route('contact')" variant="secondary" size="sm" class="whitespace-nowrap max-xl:hidden">
                {{ __('Talk to us') }}
            </x-ui.button>
            <x-ui.button tag="a" :href="route('eligibility.index')" size="sm" class="whitespace-nowrap border border-transparent">
                {{ __('Check Eligibility') }}
            </x-ui.button>
        </div>

        <button
            type="button"
            class="-mr-2 flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg text-ink lg:hidden"
            @click="toggle()"
            :aria-expanded="open"
            aria-controls="mobile-nav"
            aria-label="{{ __('Toggle navigation menu') }}"
        >
            <svg x-show="!open" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" d="M3 5.5h14M3 10h14M3 14.5h14" /></svg>
            <svg x-show="open" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" d="m4 4 12 12M16 4 4 16" /></svg>
        </button>

        <div
            x-show="open"
            x-cloak
            id="mobile-nav"
            :style="menuHeight !== null ? `max-height: ${menuHeight}px` : ''"
            class="absolute inset-x-0 top-full max-h-[calc(100dvh-var(--site-header-height))] overflow-y-auto overscroll-contain border-b border-line bg-bg px-6 py-5 shadow-lg lg:hidden"
        >
            <nav class="flex flex-col gap-4" aria-label="{{ __('Primary') }}">
                @if (count($loanCategories))
                    <div x-data="{ loansOpen: false }">
                        <button
                            type="button"
                            @click="loansOpen = !loansOpen"
                            :aria-expanded="loansOpen"
                            class="flex w-full cursor-pointer items-center justify-between text-sm font-medium text-ink-muted hover:text-ink"
                        >
                            {{ __('Loans') }}
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': loansOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
                        </button>
                        <div x-show="loansOpen" x-cloak class="mt-3 flex flex-col gap-2 border-l border-line pl-4">
                            @foreach ($loanCategories as $category)
                                <div class="flex items-center justify-between gap-3">
                                    <x-site.nav-link :route="$category['overviewRoute']" :parameters="$category['overviewParams']" :label="$category['label']" />
                                    <x-site.nav-link :route="$category['applyRoute']" :parameters="$category['applyParams']" :label="__('Apply →')" class="shrink-0 text-xs font-medium !text-accent" />
                                </div>
                            @endforeach
                            <x-site.nav-link route="loans.index" :label="__('View all loans →')" class="font-medium !text-accent" />
                        </div>
                    </div>
                @else
                    <x-site.nav-link route="loans.index" :label="__('Loans')" />
                @endif

                @if ($debtConsolidationLive)
                    <div class="flex items-center justify-between gap-3" data-mobile-debt-consolidation>
                        <a href="{{ route('debt-consolidation.hub') }}" @class([
                            'inline-flex items-center gap-1.5 text-sm font-semibold transition-colors',
                            'text-accent' => request()->routeIs('debt-consolidation.*'),
                            'text-ink hover:text-accent' => ! request()->routeIs('debt-consolidation.*'),
                        ])>
                            <span class="nav-champagne-badge rounded-full px-1.5 font-mono text-[0.6rem] font-semibold uppercase leading-4 tracking-wider">{{ __('New') }}</span>
                            {{ __('Debt Consolidation') }}
                        </a>
                        @if (in_array('assessment', \App\Support\Pages\DebtConsolidationPages::publishedKeys(), true))
                            <a href="{{ route('debt-consolidation.assessment') }}" class="shrink-0 text-xs font-medium text-accent hover:underline">{{ __('Free assessment →') }}</a>
                        @endif
                    </div>
                @endif

                <div x-data="{ creditScoreOpen: false }">
                    <button
                        type="button"
                        @click="creditScoreOpen = !creditScoreOpen"
                        :aria-expanded="creditScoreOpen"
                        class="nav-champagne -mx-3 flex w-[calc(100%+1.5rem)] cursor-pointer items-center gap-1 rounded-xl px-3 py-2 text-sm font-bold"
                    >
                        <span class="nav-champagne-badge mr-0.5 rounded-full px-1.5 font-mono text-[0.6rem] font-semibold uppercase leading-4 tracking-wider">{{ __('Free') }}</span>
                        <span class="flex-1 text-left">{{ __('Credit Score') }}</span>
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': creditScoreOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
                    </button>
                    <div x-show="creditScoreOpen" x-cloak class="mt-3 flex flex-col gap-2 border-l border-line pl-4">
                        <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'cibil']" :label="__('Free :bureau Score', ['bureau' => 'CIBIL'])" />
                        <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'experian']" :label="__('Free :bureau Score', ['bureau' => 'Experian'])" />
                        <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'equifax']" :label="__('Free :bureau Score', ['bureau' => 'Equifax'])" />
                        <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'crif']" :label="__('Free :bureau Score', ['bureau' => 'CRIF'])" />
                    </div>
                </div>

                <div x-data="{ calculatorsOpen: false }">
                    <button
                        type="button"
                        @click="calculatorsOpen = !calculatorsOpen"
                        :aria-expanded="calculatorsOpen"
                        class="flex w-full cursor-pointer items-center justify-between text-sm font-medium text-ink-muted hover:text-ink"
                    >
                        {{ __('Calculators') }}
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': calculatorsOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
                    </button>
                    <div x-show="calculatorsOpen" x-cloak class="mt-3 flex flex-col gap-4 border-l border-line pl-4">
                        @foreach (\App\Support\Calculators\CalculatorCatalog::groups() as $groupLabel => $items)
                            <div>
                                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ $groupLabel }}</p>
                                <div class="mt-2 flex flex-col gap-1.5">
                                    @foreach ($items as $item)
                                        <x-site.nav-link :route="$item['route']" :parameters="$item['params']" :label="$item['label']" />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <x-site.nav-link route="calculators.index" :label="__('View all calculators →')" class="font-medium !text-accent" />
                    </div>
                </div>

                <x-site.nav-link route="resources.index" :label="__('Resources')" />
                <x-site.nav-link route="faqs.index" :label="__('FAQs')" />
                <x-site.nav-link route="about" :label="__('About')" />
            </nav>
            <div class="mt-5 flex flex-col gap-3">
                <x-ui.button tag="a" :href="route('contact')" variant="secondary">
                    {{ __('Talk to us') }}
                </x-ui.button>
                <x-ui.button tag="a" :href="route('eligibility.index')">
                    {{ __('Check Eligibility') }}
                </x-ui.button>
            </div>
            <div class="mt-5 flex items-center justify-between gap-3 border-t border-line pt-4">
                <span class="text-sm font-medium text-ink-muted" aria-hidden="true">Language · <span lang="hi">भाषा</span></span>
                <x-site.language-switch />
            </div>
        </div>
    </div>
</header>
</div>
