<x-layouts.app :title="$loanProduct->seoTitle()" :description="$loanProduct->seoDescription()">
    <section class="mx-auto max-w-5xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Loans' => route('loans.index'), $loanProduct->name => null]" />

        <x-ui.badge tone="accent" class="mt-5">{{ $loanProduct->category->getLabel() }}</x-ui.badge>
        <h1 class="mt-4 max-w-2xl text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $loanProduct->name }}
        </h1>
        @if ($loanProduct->summary)
            <p class="mt-3 max-w-2xl text-lg text-ink-muted">{{ $loanProduct->summary }}</p>
        @endif

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button tag="a" :href="route('loans.apply', $loanProduct)" size="lg">
                Check Your Eligibility
            </x-ui.button>
            <x-ui.button tag="a" :href="route('calculators.index')" variant="secondary" size="lg">
                Calculate EMI
            </x-ui.button>
        </div>

        @if ($loanProduct->body)
            <div class="prose prose-neutral mt-12 max-w-2xl text-ink-muted [&_h2]:font-display [&_h2]:text-ink [&_p]:leading-relaxed">
                {!! $loanProduct->body !!}
            </div>
        @endif

        @if (! empty($loanProduct->features))
            <div class="mt-12">
                <h2 class="font-display text-xl font-semibold text-ink">Key features</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($loanProduct->features as $feature)
                        <li class="flex items-start gap-2.5 text-sm text-ink-muted">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-pass" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($loanProduct->eligibility_points))
            <div class="mt-12">
                <h2 class="font-display text-xl font-semibold text-ink">Eligibility at a glance</h2>
                <p class="mt-2 text-sm text-ink-faint">A general guide — the exact criteria vary by lender and are checked precisely when you apply.</p>
                <ul class="mt-4 flex flex-col gap-2">
                    @foreach ($loanProduct->eligibility_points as $point)
                        <li class="text-sm text-ink-muted">— {{ $point }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($loanProduct->documents_required))
            <div class="mt-12">
                <h2 class="font-display text-xl font-semibold text-ink">Documents you'll need</h2>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($loanProduct->documents_required as $document)
                        <x-ui.badge tone="muted">{{ $document }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
        @endif

        @if (! empty($loanProduct->process_steps))
            <div class="mt-12">
                <h2 class="font-display text-xl font-semibold text-ink">How it works</h2>
                <ol class="mt-4 grid gap-3">
                    @foreach ($loanProduct->process_steps as $index => $step)
                        <li class="flex items-center gap-3 text-sm text-ink-muted">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-[0.65rem] font-semibold text-accent">{{ $index + 1 }}</span>
                            {{ $step }}
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($loanProduct->lenderProducts->isNotEmpty())
            <div class="mt-12">
                <h2 class="font-display text-xl font-semibold text-ink">Lenders offering this product</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($loanProduct->lenderProducts as $offer)
                        <x-ui.card>
                            <div class="flex items-center gap-3">
                                <x-ui.lender-logo :lender="$offer->lender" size="sm" />
                                <p class="font-medium text-ink">{{ $offer->lender->name }}</p>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-y-1.5 font-mono text-xs text-ink-muted">
                                @if ($offer->min_amount || $offer->max_amount)
                                    <dt>Amount</dt>
                                    <dd class="text-right">₹{{ number_format((float) $offer->min_amount) }}–{{ number_format((float) $offer->max_amount) }}</dd>
                                @endif
                                @if ($offer->interest_rate_from)
                                    <dt>Rate from</dt>
                                    <dd class="text-right">{{ $offer->interest_rate_from }}%</dd>
                                @endif
                                @if ($offer->min_tenure_months || $offer->max_tenure_months)
                                    <dt>Tenure</dt>
                                    <dd class="text-right">{{ $offer->min_tenure_months }}–{{ $offer->max_tenure_months }} mo</dd>
                                @endif
                            </dl>
                        </x-ui.card>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-ink-faint">Indicative terms shared by each lender — subject to their final verification and underwriting.</p>
            </div>
        @endif

        @if ($loanProduct->faqs->isNotEmpty())
            <div class="mt-12">
                <h2 class="font-display text-xl font-semibold text-ink">Frequently asked questions</h2>
                <div class="mt-4 flex flex-col divide-y divide-line border-y border-line">
                    @foreach ($loanProduct->faqs as $faq)
                        <details class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-ink">
                                {{ $faq->question }}
                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                            </summary>
                            <p class="mt-3 text-sm text-ink-muted">{{ $faq->answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</x-layouts.app>
