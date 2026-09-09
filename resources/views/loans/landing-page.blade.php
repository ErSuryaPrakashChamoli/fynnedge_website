<x-layouts.app handles-faqs :title="$landingPage->seoTitle()" :description="$landingPage->seoDescription()" :canonical="$landingPage->seoCanonicalUrl()" :og-image="$landingPage->seoOgImageUrl()" :robots="$landingPage->seoRobots()" :structured-data="$landingPage->seoStructuredData()" :schema-nodes="$schemaNodes">
    {{--
        Same microdata + data-ai-context treatment as loans/show.blade.php —
        see the comment there for why the FAQ accordion is deliberately left
        out of the microdata.
    --}}
    <article class="mx-auto max-w-5xl px-6 py-14 lg:px-8" itemscope itemtype="https://schema.org/Service">
        <x-ui.breadcrumbs :trail="[
            'Loans' => route('loans.index'),
            $loanProduct->name => route('loans.show', $loanProduct),
            $landingPage->title => null,
        ]" />

        <link itemprop="url" href="{{ route('loans.landing-pages.show', ['loanProduct' => $loanProduct, 'landingPage' => $landingPage]) }}">
        <meta itemprop="serviceType" content="{{ $loanProduct->category->getLabel() }}">

        <div data-reveal="left">
            <x-ui.badge tone="accent" class="mt-5" itemprop="category">{{ $loanProduct->category->getLabel() }}</x-ui.badge>
            <h1 itemprop="name" class="mt-4 max-w-2xl text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
                {{ $landingPage->title }}
            </h1>
            @if ($landingPage->excerpt)
                <p itemprop="description" class="mt-3 max-w-2xl text-lg text-ink-muted">{{ $landingPage->excerpt }}</p>
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
            <div data-reveal="down" class="mt-12" data-ai-context="EMI Calculator">
                <h2 class="font-display text-xl font-semibold text-ink">{{ $landingPage->title }} EMI calculator</h2>
                <p class="mt-2 text-sm text-ink-faint">Estimate your monthly instalment and see the full year-by-year principal and interest breakdown.</p>
                <div class="mt-6">
                    {{--
                        show-loan-details is false for the same reason it is on
                        loans/show.blade.php: this page already renders the FAQ
                        accordion, its JSON-LD, the lender comparison and the
                        related content that the calculator's "About this loan"
                        panel would otherwise repeat. Leaving it on emitted a
                        second FAQPage node carrying the *same* #faq @id as the
                        one below, and showed every FAQ twice.
                    --}}
                    <livewire:emi-calculator :category="$loanProduct->category->value" :show-loan-details="false" :key="'calc-'.$landingPage->id" />
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

        <x-site.faq-accordion :faqs="$faqs" data-ai-context="Frequently Asked Questions" />
        <x-site.faq-json-ld :faqs="$faqs" />
    </article>
</x-layouts.app>
