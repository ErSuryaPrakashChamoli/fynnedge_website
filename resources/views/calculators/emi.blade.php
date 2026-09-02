<x-layouts.app :title="$category->getLabel().' EMI Calculator'" :description="'Estimate your monthly EMI for a '.$category->getLabel().' and see the full year-by-year principal and interest breakdown.'">
    <section class="mx-auto max-w-4xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => route('calculators.index'), $category->getLabel().' EMI Calculator' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $category->getLabel() }} EMI Calculator
        </h1>
        <p data-reveal="up" class="mt-3 max-w-xl text-ink-muted">
            Adjust the amount, interest rate and tenure to see your monthly EMI —
            plus the full year-by-year principal and interest breakdown. You can switch loan types below.
        </p>

        <div data-reveal="zoom" class="mt-10">
            @if ($category->isHybridRepayment())
                <livewire:flexi-hybrid-calculator :key="'calc-'.$category->value" />
            @else
                <livewire:emi-calculator :category="$category->value" :key="'calc-'.$category->value" />
            @endif
        </div>
    </section>
</x-layouts.app>
