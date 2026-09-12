<x-layouts.app handles-faqs :title="$landingPage->seoTitle()" :description="$landingPage->seoDescription()" :canonical="$landingPage->seoCanonicalUrl()" :og-image="$landingPage->seoOgImageUrl()" :social="$landingPage->seoSocial()" :robots="$landingPage->seoRobots()" :structured-data="$landingPage->seoStructuredData()" :page-type="$landingPage->seoPageType()" :schema-template="$landingPage->seoSchemaTemplate()" :schema-nodes="$schemaNodes">
    {{--
        Same microdata + data-ai-context treatment as loans/show.blade.php —
        see the comment there for why the FAQ accordion is deliberately left
        out of the microdata.
    --}}
    {{--
        Same hero-with-form treatment as loans/show.blade.php. The landing page's
        own title is what :product resolves to in the admin-editable heading, so
        "Apply for a :product" reads as this page's subject rather than the
        parent product's. The lead still points at the parent loan product;
        source_url is what preserves which of the two pages it came from.
    --}}
    <div itemscope itemtype="https://schema.org/Service">
        <link itemprop="url" href="{{ route('loans.landing-pages.show', ['loanProduct' => $loanProduct, 'landingPage' => $landingPage]) }}">
        <meta itemprop="serviceType" content="{{ $loanProduct->category->getLabel() }}">

        <x-site.loan-enquiry
            :loan-product="$loanProduct"
            :label="$landingPage->title"
            :description="$landingPage->excerpt ?: $loanProduct->summary"
        >
            <x-slot:breadcrumbs>
                <x-ui.breadcrumbs :trail="[
                    'Loans' => route('loans.index'),
                    $loanProduct->name => route('loans.show', $loanProduct),
                    $landingPage->title => null,
                ]" />
            </x-slot:breadcrumbs>

            <x-ui.button tag="a" :href="route('loans.apply', $loanProduct)">
                {{ $landingPage->cta_label ?: 'Check Your Eligibility' }}
            </x-ui.button>
            <x-ui.button tag="a" :href="route('loans.show', $loanProduct)" variant="secondary">
                {{ $loanProduct->name }} overview
            </x-ui.button>
        </x-site.loan-enquiry>

    <article class="mx-auto max-w-7xl px-6 pb-14 lg:px-8">
        @if ($landingPage->body)
            <div data-reveal="fade" class="prose prose-neutral mt-12 max-w-3xl text-ink-muted [&_h2]:font-display [&_h2]:text-ink [&_p]:leading-relaxed">
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
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
    </div>
</x-layouts.app>
