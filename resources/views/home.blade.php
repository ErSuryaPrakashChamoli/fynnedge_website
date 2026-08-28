<x-layouts.app>
    <section class="mx-auto max-w-7xl px-6 pb-20 pt-16 lg:px-8 lg:pt-24">
        <p class="font-mono text-xs font-semibold uppercase tracking-[0.14em] text-accent">FynnEdge Advisory (OPC) Pvt Ltd</p>
        <h1 class="mt-5 max-w-3xl text-balance font-display text-4xl font-semibold leading-[1.08] tracking-tight text-ink sm:text-5xl lg:text-6xl">
            Simplifying loans.
            <span class="text-accent">Amplifying trust.</span>
        </h1>
        <p class="mt-6 max-w-xl text-lg text-ink-muted">
            FynnEdge connects you with suitable banks and NBFCs for personal loans, home loans,
            business loans and loans against property — with clear, upfront eligibility before you apply.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button :tag="Route::has('eligibility.index') ? 'a' : 'button'" :href="Route::has('eligibility.index') ? route('eligibility.index') : null" size="lg">
                Check Your Eligibility
            </x-ui.button>
            <x-ui.button :tag="Route::has('loans.index') ? 'a' : 'button'" :href="Route::has('loans.index') ? route('loans.index') : null" variant="secondary" size="lg">
                Explore Loan Products
            </x-ui.button>
        </div>
    </section>

    <section class="border-t border-line bg-surface">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="flex items-center gap-3">
                <x-ui.badge tone="warn">Foundation phase</x-ui.badge>
                <p class="font-mono text-xs text-ink-faint">Phase 2 of 15 — design system</p>
            </div>
            <h2 class="mt-4 max-w-2xl text-balance font-display text-2xl font-semibold text-ink">
                The public site, CMS and loan journey are being built module by module.
            </h2>
            <p class="mt-3 max-w-2xl text-ink-muted">
                This page exists to prove out the design system — typography, color, navigation and
                core components — that every loan product page, calculator and journey step will share.
            </p>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.card>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Component</p>
                    <p class="mt-2 font-display text-lg font-semibold text-ink">Buttons</p>
                    <div class="mt-4 flex flex-wrap gap-2.5">
                        <x-ui.button size="sm">Primary</x-ui.button>
                        <x-ui.button variant="secondary" size="sm">Secondary</x-ui.button>
                        <x-ui.button variant="ghost" size="sm">Ghost</x-ui.button>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Component</p>
                    <p class="mt-2 font-display text-lg font-semibold text-ink">Status badges</p>
                    <div class="mt-4 flex flex-wrap gap-2.5">
                        <x-ui.badge tone="pass">Eligible</x-ui.badge>
                        <x-ui.badge tone="warn">Review</x-ui.badge>
                        <x-ui.badge tone="accent">New</x-ui.badge>
                        <x-ui.badge tone="muted">Draft</x-ui.badge>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Component</p>
                    <p class="mt-2 font-display text-lg font-semibold text-ink">Journey stepper</p>
                    <x-ui.stepper class="mt-4" :steps="['Basic details', 'Employment & income', 'Eligibility results']" :current="1" />
                </x-ui.card>
            </div>

            <div class="mt-6">
                <x-ui.alert tone="accent" title="Indicative, not guaranteed">
                    Eligibility results shown anywhere on this site are indicative and subject to
                    lender verification, documentation and final underwriting.
                </x-ui.alert>
            </div>
        </div>
    </section>
</x-layouts.app>
