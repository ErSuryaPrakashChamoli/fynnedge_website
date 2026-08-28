<x-layouts.app title="EMI Calculator" description="Estimate your monthly EMI for a personal loan, home loan, business loan or loan against property.">
    <section class="mx-auto max-w-4xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => null]" />

        <h1 class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            EMI Calculator
        </h1>
        <p class="mt-3 max-w-xl text-ink-muted">
            Adjust the loan amount, interest rate and tenure to estimate your monthly instalment.
            Works for any loan type — the same math applies whether it's personal, home, business or against property.
        </p>

        <div class="mt-10">
            <livewire:emi-calculator />
        </div>
    </section>
</x-layouts.app>
