<x-layouts.app handles-faqs handles-video-testimonials :title="$loanProduct->seoTitle()" :description="$loanProduct->seoDescription()" :canonical="$loanProduct->seoCanonicalUrl()" :og-image="$loanProduct->seoOgImageUrl()" :social="$loanProduct->seoSocial()" :robots="$loanProduct->seoRobots()" :structured-data="$loanProduct->seoStructuredData()" :page-type="$loanProduct->seoPageType()" :schema-template="$loanProduct->seoSchemaTemplate()" :schema-nodes="$schemaNodes">
    {{--
        Microdata on the page's own markup, alongside the JSON-LD in the head.
        Search crawlers and AI assistants that parse the rendered DOM rather
        than the script tag get the same product identity, and the
        data-ai-context labels name each block's subject in plain language.
        Deliberately NOT applied to the FAQ accordion below: the FAQPage
        JSON-LD already declares those Question/Answer entities, and marking
        them up twice would declare them twice.
    --}}
    {{--
        The hero IS the enquiry section. The product's own heading, summary and
        CTAs sit in its left column and the form in its right, so the form is on
        screen when the page loads rather than a scroll away — which is the whole
        point of putting it on a loan page. The page title (h1) lives inside the
        component; nothing here renders a second one.

        Full-bleed section, then the article below it: both inner containers are
        max-w-7xl px-6 lg:px-8, matching the site header and footer exactly, so
        every left edge on the page lines up.
    --}}
    <div itemscope itemtype="https://schema.org/Service">
        <link itemprop="url" href="{{ route('loans.show', $loanProduct) }}">
        <meta itemprop="serviceType" content="{{ $loanProduct->category->getLabel() }}">

        <x-site.loan-enquiry :loan-product="$loanProduct" :eyebrow="$loanProduct->marketing_headline">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumbs :trail="[__('Loans') => route('loans.index'), $loanProduct->name => null]" />
            </x-slot:breadcrumbs>

            <x-ui.button tag="a" :href="route('loans.apply', $loanProduct)">
                {{ $loanProduct->cta_label ?: __('Check Your Eligibility') }}
            </x-ui.button>
            <x-ui.button tag="a" :href="$calculatorSupported ? route('calculators.emi', $loanProduct->category->value) : route('calculators.index')" variant="secondary">
                {{ __('Calculate EMI') }}
            </x-ui.button>
        </x-site.loan-enquiry>

    <article class="mx-auto max-w-7xl px-6 pb-14 lg:px-8">
        {{--
            Below the enquiry form, so never the page's Largest Contentful Paint
            (the heading's text is), yet the full upload (774KB on Personal Loan)
            used to download alongside it. Lazy, WebP once its copies exist, and
            sized from the upload so its box is reserved before it arrives.
        --}}
        @if ($loanProduct->imageUrl())
            @php
                $imageSrcset = $loanProduct->imageSrcset();
                $imageSize = $loanProduct->imageDimensions();
            @endphp
            <picture class="block">
                @if ($imageSrcset)
                    <source type="image/webp" srcset="{{ $imageSrcset }}" sizes="(min-width: 1280px) 1216px, (min-width: 1024px) calc(100vw - 64px), calc(100vw - 48px)">
                @endif
                <img
                    src="{{ $loanProduct->imageUrl() }}"
                    alt="{{ $loanProduct->image_alt ?? '' }}"
                    @if ($imageSize) width="{{ $imageSize[0] }}" height="{{ $imageSize[1] }}" @endif
                    loading="lazy"
                    decoding="async"
                    class="mt-12 max-h-80 w-full rounded-2xl object-cover"
                >
            </picture>
        @endif

        @if (! empty($loanProduct->benefits))
            <div class="mt-12" data-ai-context="Product Benefits">
                <h2 data-reveal="zoom" class="font-display text-xl font-semibold text-ink">{{ __('Benefits') }}</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
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
            <div data-reveal="fade" class="rich-text mt-12 max-w-none text-ink-muted text-justify hyphens-auto">
                {!! \App\Support\Rates\RateText::render($loanProduct->body) !!}
            </div>
        @endif

        @if (! empty($loanProduct->features))
            <div class="mt-12" data-ai-context="Product Features">
                <h2 data-reveal="right" class="font-display text-xl font-semibold text-ink">{{ __('Key features') }}</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
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
                <h2 data-reveal="up" class="font-display text-xl font-semibold text-ink">{{ __('Eligibility at a glance') }}</h2>
                <p class="mt-2 text-sm text-ink-faint">{{ __('A general guide — the exact criteria vary by lender and are checked precisely when you apply.') }}</p>
                <ul class="mt-4 flex max-w-3xl flex-col gap-2">
                    @foreach ($loanProduct->eligibility_points as $point)
                        <li data-reveal="left stagger" class="text-sm text-ink-muted">— {{ $point }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($loanProduct->documents_required))
            <div class="mt-12" data-ai-context="Required Documents">
                <h2 data-reveal="zoom" class="font-display text-xl font-semibold text-ink">{{ __("Documents you'll need") }}</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($loanProduct->documents_required as $document)
                        <x-ui.badge data-reveal="zoom stagger" tone="muted">{{ $document }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
        @endif

        @if (! empty($loanProduct->process_steps))
            <div class="mt-12" data-ai-context="Application Process">
                <h2 data-reveal="left" class="font-display text-xl font-semibold text-ink">{{ __('How it works') }}</h2>
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
                <h2 class="font-display text-xl font-semibold text-ink">{{ __(':product EMI calculator', ['product' => $loanProduct->name]) }}</h2>
                <p class="mt-2 text-sm text-ink-faint">{{ __('Estimate your monthly instalment and see the full year-by-year principal and interest breakdown.') }}</p>
                <div class="mt-6">
                    <livewire:emi-calculator :category="$loanProduct->category->value" :show-loan-details="false" :key="'calc-'.$loanProduct->id" />
                </div>
            </div>
        @endif


        <x-site.lender-comparison-table :offers="$loanProduct->lenderProducts" :loan-product="$loanProduct" />

        @if ($loanProduct->landingPages->isNotEmpty())
            <div class="mt-12">
                <h2 data-reveal="fade" class="font-display text-xl font-semibold text-ink">{{ __('Explore :product by amount, type, need & location', ['product' => $loanProduct->name]) }}</h2>
                <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (collect(\App\Enums\LandingPageGroup::cases())->mapWithKeys(fn ($group) => [$group->getLabel() => $loanProduct->landingPages->where('group', $group)])->filter->isNotEmpty() as $groupLabel => $pages)
                        <div data-reveal="up stagger">
                            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ $groupLabel }}</p>
                            <ul @class([
                                'mt-2 gap-1.5',
                                'grid grid-cols-2 gap-x-4' => $groupLabel === \App\Enums\LandingPageGroup::ByLocation->getLabel(),
                                'flex flex-col' => $groupLabel !== \App\Enums\LandingPageGroup::ByLocation->getLabel(),
                            ])>
                                @foreach ($pages as $page)
                                    <li>
                                        <a href="{{ route('loans.landing-pages.show', ['loanProduct' => $loanProduct, 'landingPage' => $page]) }}" @if ($page->shortLabel() !== $page->title) aria-label="{{ $page->title }}" @endif class="text-sm font-medium text-ink-muted transition-colors hover:text-ink">
                                            {{ $page->shortLabel() }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <x-site.why-fynnedge class="mt-12" :heading="__('You could go directly to a bank. But why apply for a :product through us?', ['product' => $loanProduct->name])" />

        <x-site.testimonials :testimonials="$testimonials" />

        <x-site.video-testimonials embedded class="mt-12" />

        <x-site.faq-accordion :faqs="$faqs" data-ai-context="Frequently Asked Questions" />
        <x-site.faq-json-ld :faqs="$faqs" />
    </article>
    </div>
</x-layouts.app>
