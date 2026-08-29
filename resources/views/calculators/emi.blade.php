<x-layouts.app title="EMI Calculator" description="Estimate your monthly EMI for a personal loan, home loan, business loan or loan against property.">
    <section class="mx-auto max-w-4xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => null]" />

        <h1 class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            EMI Calculator
        </h1>
        <p class="mt-3 max-w-xl text-ink-muted">
            Pick a loan type, then adjust the amount, interest rate and tenure to see your monthly EMI —
            plus the full year-by-year principal and interest breakdown.
        </p>

        <div class="mt-10">
            <livewire:emi-calculator />
        </div>
    </section>
</x-layouts.app>
