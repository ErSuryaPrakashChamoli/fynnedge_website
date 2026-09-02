<header class="sticky top-0 z-40 border-b border-line bg-bg/85 backdrop-blur">
    <div x-data="{ open: false }" class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4 lg:px-8">
        <a href="{{ route('home') }}" class="flex flex-col text-ink">
            <span class="flex items-center gap-2 font-display text-xl font-semibold tracking-tight">
                <img src="{{ $siteBranding['logoUrl'] }}" alt="" class="h-9 w-9" width="36" height="36">
                <span class="flex items-baseline gap-2">
                    {{ $siteBranding['name'] }}
                    <span class="hidden font-mono text-[0.62rem] font-medium uppercase tracking-[0.14em] text-ink-faint sm:inline">Advisory</span>
                </span>
            </span>
            <span class="mt-0.5 font-display text-[0.65rem] italic text-accent">{{ $siteBranding['tagline'] }}</span>
        </a>

        @php $loanCategories = \App\Support\Loans\LoanMegaMenu::categories(); @endphp

        <nav class="hidden items-center gap-8 lg:flex" aria-label="Primary">
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
                        Loans
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': open }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        @click="open = false"
                        class="absolute left-0 top-full z-10 w-max pt-3"
                    >
                        <div class="nav-dropdown-panel grid grid-cols-[max-content_1fr] rounded-xl border border-line bg-surface shadow-lg">
                            <div class="flex flex-col gap-1 border-r border-line p-3">
                                @foreach ($loanCategories as $category)
                                    <a
                                        href="{{ route($category['overviewRoute'], $category['overviewParams']) }}"
                                        @mouseenter="active = '{{ $category['overviewParams']['loanProduct'] }}'"
                                        class="whitespace-nowrap rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors"
                                        :class="active === '{{ $category['overviewParams']['loanProduct'] }}' ? 'bg-accent-soft text-accent' : 'text-ink-muted hover:bg-surface-2 hover:text-ink'"
                                    >
                                        {{ $category['label'] }}
                                    </a>
                                @endforeach
                            </div>

                            <div class="p-5">
                                @foreach ($loanCategories as $category)
                                    <div x-show="active === '{{ $category['overviewParams']['loanProduct'] }}'" x-cloak>
                                        <div class="flex items-center justify-between gap-4 border-b border-line pb-4">
                                            <div>
                                                <p class="font-display text-base font-semibold text-ink">{{ $category['label'] }}</p>
                                                <a href="{{ route($category['overviewRoute'], $category['overviewParams']) }}" class="text-xs font-medium text-ink-faint hover:text-ink">View overview →</a>
                                            </div>
                                            <x-ui.button tag="a" :href="route($category['applyRoute'], $category['applyParams'])" size="sm">
                                                Check Eligibility
                                            </x-ui.button>
                                        </div>

                                        @if (! empty($category['groups']))
                                            <div class="mt-4 grid grid-cols-[repeat(3,max-content)] gap-6">
                                                @foreach ($category['groups'] as $groupLabel => $items)
                                                    <div>
                                                        <p class="font-mono text-[0.62rem] font-semibold uppercase tracking-wider text-ink-faint">{{ $groupLabel }}</p>
                                                        <div class="mt-2 flex flex-col">
                                                            @foreach ($items as $item)
                                                                <x-site.nav-link :route="$item['route']" :parameters="$item['params']" :label="$item['label']" class="block max-w-[14rem] rounded-lg px-2 py-1 text-sm leading-snug hover:bg-surface-2" />
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <x-site.nav-link route="loans.index" label="Loans" />
            @endif
            <x-site.nav-dropdown label="Credit Score" activeWhen="credit-score.*">
                <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'cibil']" label="Free CIBIL Score" class="block whitespace-nowrap rounded-lg px-3 py-1 hover:bg-surface-2" />
                <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'experian']" label="Free Experian Score" class="block whitespace-nowrap rounded-lg px-3 py-1 hover:bg-surface-2" />
                <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'equifax']" label="Free Equifax Score" class="block whitespace-nowrap rounded-lg px-3 py-1 hover:bg-surface-2" />
                <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'crif']" label="Free CRIF Score" class="block whitespace-nowrap rounded-lg px-3 py-1 hover:bg-surface-2" />
            </x-site.nav-dropdown>
            <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false" @mouseenter="open = true" @mouseleave="open = false" class="relative">
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
                    Calculators
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': open }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
                </button>

                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    @click="open = false"
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
                            <x-site.nav-link route="calculators.index" label="View all calculators →" class="block px-3 text-sm font-medium !text-accent hover:underline" />
                        </div>
                    </div>
                </div>
            </div>

            <x-site.nav-link route="eligibility.index" label="Eligibility" />
            <x-site.nav-link route="resources.index" label="Resources" />
            <x-site.nav-link route="faqs.index" label="FAQs" />
            <x-site.nav-link route="about" label="About" />
        </nav>

        <div class="hidden items-center gap-3 lg:flex">
            <x-ui.button tag="a" :href="route('contact')" variant="secondary" size="sm">
                Talk to us
            </x-ui.button>
            <x-ui.button tag="a" :href="route('eligibility.index')" size="sm">
                Check Eligibility
            </x-ui.button>
        </div>

        <button
            type="button"
            class="-mr-2 flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg text-ink lg:hidden"
            @click="open = !open"
            :aria-expanded="open"
            aria-controls="mobile-nav"
            aria-label="Toggle navigation menu"
        >
            <svg x-show="!open" class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" d="M3 5.5h14M3 10h14M3 14.5h14" /></svg>
            <svg x-show="open" x-cloak class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" d="m4 4 12 12M16 4 4 16" /></svg>
        </button>

        <div
            x-show="open"
            x-cloak
            id="mobile-nav"
            class="absolute inset-x-0 top-full border-b border-line bg-bg px-6 py-5 lg:hidden"
        >
            <nav class="flex flex-col gap-4" aria-label="Primary">
                @if (count($loanCategories))
                    <div x-data="{ loansOpen: false }">
                        <button
                            type="button"
                            @click="loansOpen = !loansOpen"
                            :aria-expanded="loansOpen"
                            class="flex w-full cursor-pointer items-center justify-between text-sm font-medium text-ink-muted hover:text-ink"
                        >
                            Loans
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': loansOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
                        </button>
                        <div x-show="loansOpen" x-cloak class="mt-3 flex flex-col gap-2 border-l border-line pl-4">
                            @foreach ($loanCategories as $category)
                                <div class="flex items-center justify-between gap-3">
                                    <x-site.nav-link :route="$category['overviewRoute']" :parameters="$category['overviewParams']" :label="$category['label']" />
                                    <x-site.nav-link :route="$category['applyRoute']" :parameters="$category['applyParams']" label="Apply →" class="shrink-0 text-xs font-medium !text-accent" />
                                </div>
                            @endforeach
                            <x-site.nav-link route="loans.index" label="View all loans →" class="font-medium !text-accent" />
                        </div>
                    </div>
                @else
                    <x-site.nav-link route="loans.index" label="Loans" />
                @endif

                <div x-data="{ creditScoreOpen: false }">
                    <button
                        type="button"
                        @click="creditScoreOpen = !creditScoreOpen"
                        :aria-expanded="creditScoreOpen"
                        class="flex w-full cursor-pointer items-center justify-between text-sm font-medium text-ink-muted hover:text-ink"
                    >
                        Credit Score
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': creditScoreOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="m5 7.5 5 5 5-5" /></svg>
                    </button>
                    <div x-show="creditScoreOpen" x-cloak class="mt-3 flex flex-col gap-2 border-l border-line pl-4">
                        <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'cibil']" label="Free CIBIL Score" />
                        <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'experian']" label="Free Experian Score" />
                        <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'equifax']" label="Free Equifax Score" />
                        <x-site.nav-link route="credit-score.show" :parameters="['bureau' => 'crif']" label="Free CRIF Score" />
                    </div>
                </div>

                <div x-data="{ calculatorsOpen: false }">
                    <button
                        type="button"
                        @click="calculatorsOpen = !calculatorsOpen"
                        :aria-expanded="calculatorsOpen"
                        class="flex w-full cursor-pointer items-center justify-between text-sm font-medium text-ink-muted hover:text-ink"
                    >
                        Calculators
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
                        <x-site.nav-link route="calculators.index" label="View all calculators →" class="font-medium !text-accent" />
                    </div>
                </div>

                <x-site.nav-link route="eligibility.index" label="Eligibility" />
                <x-site.nav-link route="resources.index" label="Resources" />
                <x-site.nav-link route="faqs.index" label="FAQs" />
                <x-site.nav-link route="about" label="About" />
            </nav>
            <div class="mt-5 flex flex-col gap-3">
                <x-ui.button tag="a" :href="route('contact')" variant="secondary">
                    Talk to us
                </x-ui.button>
                <x-ui.button tag="a" :href="route('eligibility.index')">
                    Check Eligibility
                </x-ui.button>
            </div>
        </div>
    </div>
</header>
