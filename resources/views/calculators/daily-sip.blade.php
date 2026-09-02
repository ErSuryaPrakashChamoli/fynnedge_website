<x-layouts.app title="Daily SIP Calculator" description="Estimate the maturity value of a daily SIP investment.">
    <section class="mx-auto max-w-4xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => route('calculators.index'), 'Daily SIP Calculator' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Daily SIP Calculator
        </h1>
        <p data-reveal="up" class="mt-3 max-w-xl text-ink-muted">
            Adjust the daily investment, expected return and tenure to estimate your daily SIP's maturity value.
        </p>

        <div data-reveal="zoom" class="mt-10">
            <livewire:sip-calculator frequency="daily" />
        </div>

        <x-site.calculator-explainer
            :body="$calculatorPage?->body"
            :heading="$calculatorPage?->title ?: 'About this calculator'"
            :eligibility-url="$eligibilityUrl"
        />
    </section>
</x-layouts.app>
