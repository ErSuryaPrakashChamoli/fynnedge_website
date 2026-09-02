@props(['offers', 'loanProduct'])

@php
    // Initial/Subsequent Tenure columns only apply to a hybrid-structured
    // product (Flexi Hybrid Term Loan) — every other loan type's table is
    // unaffected, since $isHybrid is false and those two <th>/<td> pairs
    // never render for them.
    $isHybrid = $loanProduct->category->isHybridRepayment();

    // Only awarded once at least two offers actually have a configured rate
    // to compare — never guessed from a single data point or from offers
    // still marked "Available on request".
    $lowestRate = $isHybrid
        ? $offers->pluck('interest_rate_from')->filter()->map(fn ($rate) => (float) $rate)->sort()->first()
        : null;
    $ratesConfiguredCount = $isHybrid ? $offers->pluck('interest_rate_from')->filter()->count() : 0;
@endphp

@if ($offers->isNotEmpty())
    <div {{ $attributes->class('mt-12') }}>
        <h2 class="font-display text-xl font-semibold text-ink">Compare lenders side by side</h2>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-line">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead>
                    <tr class="border-b border-line bg-surface-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                        <th scope="col" class="px-4 py-3">Lender</th>
                        <th scope="col" class="px-4 py-3">Type</th>
                        <th scope="col" class="px-4 py-3">Interest rate</th>
                        <th scope="col" class="px-4 py-3">Processing fee</th>
                        <th scope="col" class="px-4 py-3">Loan amount</th>
                        <th scope="col" class="px-4 py-3">Tenure</th>
                        @if ($isHybrid)
                            <th scope="col" class="px-4 py-3">Initial tenure</th>
                            <th scope="col" class="px-4 py-3">Subsequent tenure</th>
                        @endif
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Apply</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($offers as $offer)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <x-ui.lender-logo :lender="$offer->lender" size="sm" />
                                    <span class="font-medium text-ink">{{ $offer->lender->name }}</span>
                                    @if ($isHybrid && $ratesConfiguredCount > 1 && (float) $offer->interest_rate_from === $lowestRate)
                                        <x-ui.badge tone="pass">Lowest rate</x-ui.badge>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-ink-muted">
                                {{ $offer->lender->type?->getLabel() ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">
                                {{ $offer->interest_rate_from ? $offer->interest_rate_from.'%' : ($isHybrid ? 'Available on request' : '—') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">
                                {{ $offer->processingFeeDisplay() ?? ($isHybrid ? 'Available on request' : '—') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">
                                @if ($offer->min_amount || $offer->max_amount)
                                    ₹{{ number_format((float) $offer->min_amount) }}–{{ number_format((float) $offer->max_amount) }}
                                @else
                                    {{ $isHybrid ? 'Available on request' : '—' }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">
                                @if ($offer->min_tenure_months || $offer->max_tenure_months)
                                    {{ $offer->min_tenure_months }}–{{ $offer->max_tenure_months }} mo
                                @else
                                    {{ $isHybrid ? 'Available on request' : '—' }}
                                @endif
                            </td>
                            @if ($isHybrid)
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">
                                    {{ $offer->effectiveInitialTenureMonths() ? $offer->effectiveInitialTenureMonths().' mo' : 'Available on request' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">
                                    @php $subsequent = $offer->max_tenure_months ? $offer->subsequentTenureMonths($offer->max_tenure_months) : null; @endphp
                                    {{ $subsequent ? $subsequent.' mo' : 'Available on request' }}
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-ui.button tag="a" :href="route('loans.apply', $loanProduct)" size="sm">
                                    Apply Now
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-ink-faint">Indicative terms shared by each lender — subject to their final verification and underwriting.</p>
    </div>
@endif
