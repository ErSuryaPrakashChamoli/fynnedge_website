<x-layouts.app :title="$category->getLabel().' Prepayment Calculator'" :description="'See how a lumpsum prepayment reduces your '.$category->getLabel().' tenure or EMI, and how much interest you save.'">
    <section class="mx-auto max-w-4xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => route('calculators.index'), $category->getLabel().' Prepayment Calculator' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $category->getLabel() }} Prepayment Calculator
        </h1>
        <p data-reveal="up" class="mt-3 max-w-xl text-ink-muted">
            See how a lumpsum prepayment reduces your tenure or EMI, and how much interest you save.
        </p>

        <div data-reveal="zoom" class="mt-10">
            <livewire:loan-prepayment-calculator :category="$category->value" :key="'prepay-'.$category->value" />
        </div>

        <x-site.calculator-explainer
            :heading="'About the '.$category->getLabel().' Prepayment Calculator'"
            :body="$explanation"
            :eligibility-url="$eligibilityUrl"
            :apply-url="$applyUrl"
        />
    </section>
</x-layouts.app>
