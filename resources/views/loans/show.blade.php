<x-layouts.app handles-faqs :title="$loanProduct->seoTitle()" :description="$loanProduct->seoDescription()" :canonical="$loanProduct->seoCanonicalUrl()" :og-image="$loanProduct->seoOgImageUrl()" :robots="$loanProduct->seoRobots()" :structured-data="$loanProduct->seoStructuredData()" :page-type="$loanProduct->seoPageType()" :schema-template="$loanProduct->seoSchemaTemplate()" :schema-nodes="$schemaNodes">
    {{--
        Microdata on the page's own markup, alongside the JSON-LD in the head.
        Search crawlers and AI assistants that parse the rendered DOM rather
        than the script tag get the same product identity, and the
        data-ai-context labels name each block's subject in plain language.
        Deliberately NOT applied to the FAQ accordion below: the FAQPage
        JSON-LD already declares those Question/Answer entities, and marking
        them up twice would declare them twice.
    --}}
    <article class="mx-auto max-w-5xl px-6 py-14 lg:px-8" itemscope itemtype="https://schema.org/Service">
        <x-ui.breadcrumbs :trail="['Loans' => route('loans.index'), $loanProduct->name => null]" />

        <link itemprop="url" href="{{ route('loans.show', $loanProduct) }}">
        <meta itemprop="serviceType" content="{{ $loanProduct->category->getLabel() }}">

        <div data-reveal="left">
            <x-ui.badge tone="accent" class="mt-5" itemprop="category">{{ $loanProduct->category->getLabel() }}</x-ui.badge>
            @if ($loanProduct->marketing_headline)
                <p class="mt-4 font-display text-sm font-semibold uppercase tracking-wide text-accent">{{ $loanProduct->marketing_headline }}</p>
            @endif
            <h1 itemprop="name" class="mt-2 max-w-2xl text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
                {{ $loanProduct->name }}
            </h1>
            @if ($loanProduct->summary)
                <p itemprop="description" class="mt-3 max-w-2xl text-lg text-ink-muted">{{ $loanProduct->summary }}</p>
            @endif
        </div>

        @if ($loanProduct->imageUrl())
            <img
                src="{{ $loanProduct->imageUrl() }}"
                alt="{{ $loanProduct->image_alt ?? '' }}"
                class="mt-8 max-h-80 w-full rounded-2xl object-cover"
            >
        @endif

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button tag="a" :href="route('loans.apply', $loanProduct)" size="lg">
                {{ $loanProduct->cta_label ?: 'Check Your Eligibility' }}
            </x-ui.button>
            <x-ui.button tag="a" :href="$calculatorSupported ? route('calculators.emi', $loanProduct->category->value) : route('calculators.index')" variant="secondary" size="lg">
                Calculate EMI
            </x-ui.button>
        </div>

        @if (! empty($loanProduct->benefits))
            <div class="mt-12" data-ai-context="Product Benefits">
                <h2 data-reveal="zoom" class="font-display text-xl font-semibold text-ink">Benefits</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($loanProduct->benefits as $benefit)
                        <li data-reveal="up stagger" class="flex items-start gap-2.5 text-sm text-ink-muted">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-accent" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                            {{ $benefit }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($loanProduct->body)
            <div data-reveal="fade" class="prose prose-neutral mt-12 max-w-2xl text-ink-muted [&_h2]:font-display [&_h2]:text-ink [&_p]:leading-relaxed">
                {!! $loanProduct->body !!}
            </div>
        @endif

        @if (! empty($loanProduct->features))
            <div class="mt-12" data-ai-context="Product Features">
                <h2 data-reveal="right" class="font-display text-xl font-semibold text-ink">Key features</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($loanProduct->features as $feature)
                        <li data-reveal="right stagger" class="flex items-start gap-2.5 text-sm text-ink-muted">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-pass" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($loanProduct->eligibility_points))
            <div class="mt-12" data-ai-context="Product Eligibility">
                <h2 data-reveal="up" class="font-display text-xl font-semibold text-ink">Eligibility at a glance</h2>
                <p class="mt-2 text-sm text-ink-faint">A general guide — the exact criteria vary by lender and are checked precisely when you apply.</p>
                <ul class="mt-4 flex flex-col gap-2">
                    @foreach ($loanProduct->eligibility_points as $point)
                        <li data-reveal="left stagger" class="text-sm text-ink-muted">— {{ $point }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($loanProduct->documents_required))
            <div class="mt-12" data-ai-context="Required Documents">
                <h2 data-reveal="zoom" class="font-display text-xl font-semibold text-ink">Documents you'll need</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($loanProduct->documents_required as $document)
                        <x-ui.badge data-reveal="zoom stagger" tone="muted">{{ $document }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
        @endif

        @if (! empty($loanProduct->process_steps))
            <div class="mt-12" data-ai-context="Application Process">
                <h2 data-reveal="left" class="font-display text-xl font-semibold text-ink">How it works</h2>
                <ol class="mt-4 grid gap-3">
                    @foreach ($loanProduct->process_steps as $index => $step)
                        <li data-reveal="left stagger" class="flex items-center gap-3 text-sm text-ink-muted">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-[0.65rem] font-semibold text-accent">{{ $index + 1 }}</span>
                            {{ $step }}
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($calculatorSupported)
            <div data-reveal="down" class="mt-12" data-ai-context="EMI Calculator">
                <h2 class="font-display text-xl font-semibold text-ink">{{ $loanProduct->name }} EMI calculator</h2>
                <p class="mt-2 text-sm text-ink-faint">Estimate your monthly instalment and see the full year-by-year principal and interest breakdown.</p>
                <div class="mt-6">
                    <livewire:emi-calculator :category="$loanProduct->category->value" :show-loan-details="false" :key="'calc-'.$loanProduct->id" />
                </div>
            </div>
        @endif

        @if ($loanProduct->lenderProducts->isNotEmpty())
            <div class="mt-12" data-ai-context="Lender Offers">
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

        @if ($loanProduct->landingPages->isNotEmpty())
            <div class="mt-12">
                <h2 data-reveal="fade" class="font-display text-xl font-semibold text-ink">Explore {{ $loanProduct->name }} by amount, type &amp; need</h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-3">
                    @foreach ($loanProduct->landingPages->groupBy(fn ($page) => $page->group->getLabel()) as $groupLabel => $pages)
                        <div data-reveal="up stagger">
                            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ $groupLabel }}</p>
                            <ul class="mt-2 flex flex-col gap-1.5">
                                @foreach ($pages as $page)
                                    <li>
                                        <a href="{{ route('loans.landing-pages.show', ['loanProduct' => $loanProduct, 'landingPage' => $page]) }}" class="text-sm font-medium text-ink-muted transition-colors hover:text-ink">
                                            {{ $page->title }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <x-site.why-fynnedge class="mt-12" :heading="'You could go directly to a bank. But why apply for a '.$loanProduct->name.' through us?'" />

        <x-site.testimonials :testimonials="$testimonials" />

        <x-site.faq-accordion :faqs="$faqs" data-ai-context="Frequently Asked Questions" />
        <x-site.faq-json-ld :faqs="$faqs" />
    </article>
</x-layouts.app>
