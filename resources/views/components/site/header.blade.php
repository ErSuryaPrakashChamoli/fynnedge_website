<header class="sticky top-0 z-40 border-b border-line bg-bg/85 backdrop-blur">
    <div x-data="{ open: false }" class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4 lg:px-8">
        <a href="{{ route('home') }}" class="flex flex-col text-ink">
            <span class="flex items-center gap-2 font-display text-xl font-semibold tracking-tight">
                <img src="{{ asset('fynnedge-icon.png') }}" alt="" class="h-9 w-9" width="36" height="36">
                <span class="flex items-baseline gap-2">
                    FynnEdge
                    <span class="hidden font-mono text-[0.62rem] font-medium uppercase tracking-[0.14em] text-ink-faint sm:inline">Advisory</span>
                </span>
            </span>
            <span class="mt-0.5 font-display text-[0.65rem] italic text-accent">Simplifying Loan, Amplifying Trust</span>
        </a>

        <nav class="hidden items-center gap-8 lg:flex" aria-label="Primary">
            <x-site.nav-link route="loans.index" label="Loans" />
            <x-site.nav-link route="eligibility.index" label="Eligibility" />
            <x-site.nav-link route="calculators.index" label="Calculators" />
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
            class="-mr-2 flex h-10 w-10 items-center justify-center rounded-lg text-ink lg:hidden"
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
                <x-site.nav-link route="loans.index" label="Loans" />
                <x-site.nav-link route="eligibility.index" label="Eligibility" />
                <x-site.nav-link route="calculators.index" label="Calculators" />
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
