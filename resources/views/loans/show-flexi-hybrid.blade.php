@php
    $initialTenureMonths = $loanProduct->default_initial_tenure_months;
    $totalTenureMonths = $loanProduct->default_tenure_months;
    $subsequentTenureMonths = ($initialTenureMonths && $totalTenureMonths && $totalTenureMonths > $initialTenureMonths)
        ? $totalTenureMonths - $initialTenureMonths
        : null;
    $lenderNames = $loanProduct->lenderProducts->pluck('lender.name');
@endphp
<x-layouts.app handles-faqs handles-video-testimonials :title="$loanProduct->seoTitle()" :description="$loanProduct->seoDescription()" :canonical="$loanProduct->seoCanonicalUrl()" :og-image="$loanProduct->seoOgImageUrl()" :social="$loanProduct->seoSocial()" :robots="$loanProduct->seoRobots()" :structured-data="$loanProduct->seoStructuredData()" :page-type="$loanProduct->seoPageType()" :schema-template="$loanProduct->seoSchemaTemplate()" :schema-nodes="$schemaNodes">
    <x-site.flexi-hybrid-hero :loan-product="$loanProduct" />

    {{--
        Same microdata + data-ai-context treatment as loans/show.blade.php —
        see the comment there for why the FAQ accordion is deliberately left
        out of the microdata.
    --}}
    <article id="flexi-hybrid-details" class="mx-auto max-w-7xl px-6 py-14 lg:px-8" itemscope itemtype="https://schema.org/Service">
        <meta itemprop="name" content="{{ $loanProduct->name }}">
        <link itemprop="url" href="{{ route('loans.show', $loanProduct) }}">
        <meta itemprop="serviceType" content="{{ $loanProduct->category->getLabel() }}">

        {{-- This page keeps its own bespoke hero, which now carries the enquiry
             form in its right column — so there is no separate enquiry band here. --}}
        <x-ui.breadcrumbs :trail="[__('Loans') => route('loans.index'), $loanProduct->name => null]" />

        @if ($loanProduct->summary || $loanProduct->body)
            <div class="mt-8" data-reveal="fade">
                <h2 class="font-display text-xl font-semibold text-ink">{{ __('What is a Flexi Hybrid Term Loan?') }}</h2>
                @if ($loanProduct->summary)
                    <p itemprop="description" class="mt-3 text-lg text-ink-muted text-justify hyphens-auto">{{ $loanProduct->summary }}</p>
                @endif
                @if ($loanProduct->body)
                    <div class="rich-text mt-4 max-w-none text-ink-muted text-justify hyphens-auto">
                        {!! \App\Support\Rates\RateText::render($loanProduct->body) !!}
                    </div>
                @endif
            </div>
        @endif

        {{--
            USP is given preference: it's the first full section after the
            overview, ahead of the lender comparison table, and wrapped in the
            same "featured callout" treatment (rounded-3xl, bordered,
            surface-2 background) as the page's other high-priority blocks —
            not just another plain mt-12 block like the sections below it.
        --}}
        <div class="mt-12 rounded-3xl border border-line bg-surface-2 p-8" data-reveal="down">
            <p class="font-mono text-xs font-semibold uppercase tracking-[0.14em] text-accent">{{ __('The headline reasons') }}</p>
            <x-site.usp-cards
                class="!mt-3"
                :heading="__('Why Flexi Hybrid Term Loan?')"
                :cards="[
                    ['title' => __('Lower Initial Outflow'), 'body' => __('Pay interest only during the initial tenure, keeping your early monthly outflow lower than a conventional EMI — exact figures depend on your chosen lender and are shown in the calculator below.')],
                    ['title' => __('Flexible Access'), 'body' => __('Access the sanctioned facility as permitted by your chosen lender\'s terms, rather than a single upfront disbursal only.')],
                    ['title' => __('Utilisation-Based Interest'), 'body' => __('Where applicable, interest is charged on the amount actually utilised — check the specific lender\'s terms, as this varies by product.')],
                    ['title' => __('Flexible Repayment'), 'body' => __('Prepayment and repayment flexibility, subject to each lender\'s own rules and any applicable charges.')],
                    ['title' => __('Two-Stage Repayment'), 'body' => __('A clear transition from an initial interest-only period to standard principal + interest EMIs for the rest of the tenure.')],
                    ['title' => __('Compare :count Options', ['count' => max($lenderNames->count(), 4)]), 'body' => $lenderNames->isNotEmpty() ? __('Compare offers from :lenders side by side before you apply.', ['lenders' => $lenderNames->join(', ', ' '.__('and').' ')]) : __('Compare offers from FynnEdge\'s lending partners side by side before you apply.')],
                ]"
            />
        </div>

        <div class="mt-12" data-reveal="fade">
            <h2 class="font-display text-xl font-semibold text-ink">{{ __("Flexi Hybrid Term Loan vs a Personal Loan — what's different?") }}</h2>
            <p class="mt-2 text-sm text-ink-muted text-justify hyphens-auto">{{ __('Both can fund the same needs, but they repay very differently. See your exact numbers for the amount you enter in the "Flexi Hybrid vs a conventional loan" chart inside the calculator below.') }}</p>
            <div class="mt-4 overflow-x-auto rounded-2xl border border-line">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-line bg-surface-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                            <th scope="col" class="px-4 py-3">{{ __('Aspect') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('Flexi Hybrid Term Loan') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('Personal Loan') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ __('Repayment structure') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Two-stage: interest-only initial tenure, then principal + interest') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Single-stage: fixed EMI (principal + interest) from month 1') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ __('Early monthly outflow') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Lower during the initial tenure') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Full EMI from the very first month') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ __('Outflow over time') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Rises once the initial tenure ends') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Stays constant for the whole tenure') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ __('Total interest, same amount/rate/tenure') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Typically higher — less principal is repaid during the initial tenure') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Typically lower — principal reduces from month 1') }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-ink">{{ __('Best suited for') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Lower commitments now, expecting cash flow to improve later') }}</td>
                            <td class="px-4 py-3 text-ink-muted">{{ __('Predictable, equal payments from the start') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-ink-faint">{{ __('A general structural comparison — exact rates, fees and eligibility for either loan type depend on the specific lender.') }}</p>
        </div>

        <x-site.lender-comparison-table :offers="$loanProduct->lenderProducts" :loan-product="$loanProduct" />

        <div class="mt-12" data-reveal="down">
            <h2 class="font-display text-xl font-semibold text-ink">{{ __('Calculate your EMI') }}</h2>
            <p class="mt-2 text-sm text-ink-faint">{{ __('Estimate your initial (interest-only) and subsequent (principal + interest) EMIs, by lender.') }}</p>
            <div class="mt-6">
                <livewire:flexi-hybrid-calculator :key="'calc-flexi-hybrid-'.$loanProduct->id" />
            </div>
        </div>

        <x-site.repayment-stages :initial-tenure-months="$initialTenureMonths" :subsequent-tenure-months="$subsequentTenureMonths" />

        @if (! empty($loanProduct->process_steps))
            <div class="mt-12">
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

        @if (! empty($loanProduct->eligibility_points))
            <div class="mt-12">
                <h2 data-reveal="up" class="font-display text-xl font-semibold text-ink">{{ __('Eligibility at a glance') }}</h2>
                <p class="mt-2 text-sm text-ink-faint">{{ __('A general guide — the exact criteria vary by lender and are checked precisely when you apply.') }}</p>
                <ul class="mt-4 flex flex-col gap-2">
                    @foreach ($loanProduct->eligibility_points as $point)
                        <li data-reveal="left stagger" class="text-sm text-ink-muted">— {{ $point }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($loanProduct->documents_required))
            <div class="mt-12">
                <h2 data-reveal="zoom" class="font-display text-xl font-semibold text-ink">{{ __("Documents you'll need") }}</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($loanProduct->documents_required as $document)
                        <x-ui.badge data-reveal="zoom stagger" tone="muted">{{ $document }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-12 rounded-2xl border border-line bg-surface-2 p-8 text-center" data-reveal="zoom">
            <h2 class="font-display text-xl font-semibold text-ink">{{ __('Ready to apply?') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-ink-muted">{{ __('Start your Flexi Hybrid Term Loan application — the same secure application flow used across FynnEdge.') }}</p>
            <x-ui.button tag="a" :href="route('loans.apply', $loanProduct)" size="lg" class="mt-5">
                {{ $loanProduct->cta_label ?: __('Apply Now') }}
            </x-ui.button>
        </div>

        <x-site.why-fynnedge class="mt-12" :heading="__('You could go directly to a bank. But why apply for a :product through us?', ['product' => $loanProduct->name])" />

        <x-site.testimonials :testimonials="$testimonials" />

        <x-site.video-testimonials embedded class="mt-12" />

        <x-site.faq-accordion :faqs="$faqs" data-ai-context="Frequently Asked Questions" />
        <x-site.faq-json-ld :faqs="$faqs" />
    </article>
</x-layouts.app>
