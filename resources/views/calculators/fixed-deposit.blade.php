<x-layouts.app title="Fixed Deposit Calculator" description="Estimate the maturity value and interest earned on a fixed deposit.">
    <section class="mx-auto max-w-4xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => route('calculators.index'), 'Fixed Deposit Calculator' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Fixed Deposit Calculator
        </h1>
        <p data-reveal="up" class="mt-3 max-w-xl text-ink-muted">
            Adjust the deposit amount, interest rate and tenure to see the maturity value of your fixed deposit.
        </p>

        <div data-reveal="zoom" class="mt-10">
            <livewire:fixed-deposit-calculator />
        </div>

        <x-site.calculator-explainer
            :body="$calculatorPage?->body"
            :heading="$calculatorPage?->title ?: 'About this calculator'"
            :eligibility-url="$eligibilityUrl"
        />
    </section>
</x-layouts.app>
