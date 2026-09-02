<x-layouts.app title="GST Calculator" description="Add or remove GST from an amount and see the CGST/SGST split.">
    <section class="mx-auto max-w-4xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => route('calculators.index'), 'GST Calculator' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            GST Calculator
        </h1>
        <p data-reveal="up" class="mt-3 max-w-xl text-ink-muted">
            Add GST to a base amount, or work out the base amount and GST already included in a total.
        </p>

        <div data-reveal="zoom" class="mt-10">
            <livewire:gst-calculator />
        </div>

        <x-site.calculator-explainer
            :body="$calculatorPage?->body"
            :heading="$calculatorPage?->title ?: 'About this calculator'"
            :eligibility-url="$eligibilityUrl"
        />
    </section>
</x-layouts.app>
