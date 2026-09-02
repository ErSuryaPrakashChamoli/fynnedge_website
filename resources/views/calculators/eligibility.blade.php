<x-layouts.app :title="$category->getLabel().' Eligibility Calculator'" :description="'Get a quick, indicative '.$category->getLabel().' eligibility estimate based on your income and existing obligations.'">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => route('calculators.index'), $category->getLabel().' Eligibility Calculator' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $category->getLabel() }} Eligibility Calculator
        </h1>
        <p data-reveal="up" class="mt-3 max-w-xl text-ink-muted">
            Enter your details for a quick, indicative check against our lenders' published criteria.
        </p>

        <div data-reveal="zoom" class="mt-10">
            <livewire:loan-eligibility-calculator :category="$category->value" :key="'elig-'.$category->value" />
        </div>

        <x-site.calculator-explainer
            :heading="'About the '.$category->getLabel().' Eligibility Calculator'"
            :body="$explanation"
            :apply-url="$applyUrl"
        />
    </section>
</x-layouts.app>
