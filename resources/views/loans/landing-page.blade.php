<x-layouts.app :title="$landingPage->seoTitle()" :description="$landingPage->seoDescription()" :canonical="$landingPage->seoCanonicalUrl()" :og-image="$landingPage->seoOgImageUrl()" :robots="$landingPage->seoRobots()">
    <section class="mx-auto max-w-5xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="[
            'Loans' => route('loans.index'),
            $loanProduct->name => route('loans.show', $loanProduct),
            $landingPage->title => null,
        ]" />

        <div data-reveal="left">
            <x-ui.badge tone="accent" class="mt-5">{{ $loanProduct->category->getLabel() }}</x-ui.badge>
            <h1 class="mt-4 max-w-2xl text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
                {{ $landingPage->title }}
            </h1>
            @if ($landingPage->excerpt)
                <p class="mt-3 max-w-2xl text-lg text-ink-muted">{{ $landingPage->excerpt }}</p>
            @endif
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button tag="a" :href="route('loans.apply', $loanProduct)" size="lg">
                {{ $landingPage->cta_label ?: 'Check Your Eligibility' }}
            </x-ui.button>
            <x-ui.button tag="a" :href="route('loans.show', $loanProduct)" variant="secondary" size="lg">
                {{ $loanProduct->name }} overview
            </x-ui.button>
        </div>

        @if ($landingPage->body)
            <div data-reveal="fade" class="prose prose-neutral mt-12 max-w-2xl text-ink-muted [&_h2]:font-display [&_h2]:text-ink [&_p]:leading-relaxed">
                {!! $landingPage->body !!}
            </div>
        @endif

        @if ($calculatorSupported)
            <div data-reveal="down" class="mt-12">
                <h2 class="font-display text-xl font-semibold text-ink">{{ $landingPage->title }} EMI calculator</h2>
                <p class="mt-2 text-sm text-ink-faint">Estimate your monthly instalment and see the full year-by-year principal and interest breakdown.</p>
                <div class="mt-6">
                    <livewire:emi-calculator :category="$loanProduct->category->value" :key="'calc-'.$landingPage->id" />
                </div>
            </div>
        @endif

        @if ($loanProduct->lenderProducts->isNotEmpty())
            <div class="mt-12">
                <h2 data-reveal="right" class="font-display text-xl font-semibold text-ink">Lenders offering this product</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($loanProduct->lenderProducts as $offer)
                        <x-site.lender-offer-card :offer="$offer" :loan-product="$loanProduct" />
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-ink-faint">Indicative terms shared by each lender — subject to their final verification and underwriting.</p>
            </div>
        @endif

        <x-site.lender-comparison-table :offers="$loanProduct->lenderProducts" :loan-product="$loanProduct" />

        <x-site.why-fynnedge class="mt-12" :heading="'You could go directly to a bank. But why apply for a '.$landingPage->title.' through us?'" />

        <x-site.testimonials :testimonials="$testimonials" />

        <x-site.faq-accordion :faqs="$loanProduct->faqs" />
        <x-site.faq-json-ld :faqs="$loanProduct->faqs" />
    </section>
</x-layouts.app>
