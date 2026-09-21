<x-layouts.app handles-faqs :title="$content['meta_title']" :description="$content['meta_description']">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Calculators' => route('calculators.index'), $category->getLabel().' EMI Calculator' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $content['heading'] }}
        </h1>
        <p data-reveal="up" class="mt-3 text-ink-muted text-justify hyphens-auto">
            {{ $content['description'] }}
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
