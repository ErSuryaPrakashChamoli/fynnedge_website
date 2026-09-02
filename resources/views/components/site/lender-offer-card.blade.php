@props(['offer', 'loanProduct'])

<x-ui.card data-reveal="right stagger" class="card-lift transition-colors transition-shadow hover:bg-accent-soft hover:shadow-md hover:animate-card-swing">
    <div class="flex items-center gap-3">
        <x-ui.lender-logo :lender="$offer->lender" size="sm" />
        <p class="font-medium text-ink">{{ $offer->lender->name }}</p>
        @if ($offer->lender->type)
            <span class="ml-auto shrink-0 rounded-full bg-accent-soft px-2 py-0.5 font-mono text-[10px] uppercase tracking-wide text-accent">
                {{ $offer->lender->type->getLabel() }}
            </span>
        @endif
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
        @if ($offer->processingFeeDisplay() || $offer->processing_fee_note)
            <dt>Processing fee</dt>
            <dd class="text-right">
                @if ($offer->processingFeeDisplay())
                    {{ $offer->processingFeeDisplay() }}
                @endif
                @if ($offer->processing_fee_note)
                    <span class="block text-ink-faint">{{ $offer->processing_fee_note }}</span>
                @endif
            </dd>
        @endif
    </dl>
    @if ($offer->eligibilitySummaryPoints())
        <ul class="mt-3 space-y-1 border-t border-line pt-3 text-xs text-ink-muted">
            @foreach ($offer->eligibilitySummaryPoints() as $point)
                <li class="flex items-start gap-1.5">
                    <span class="text-accent">✓</span>
                    <span>{{ $point }}</span>
                </li>
            @endforeach
        </ul>
    @endif
    <x-ui.button tag="a" :href="route('loans.apply', $loanProduct)" size="sm" class="mt-4 w-full">
        Apply Now
    </x-ui.button>
</x-ui.card>
