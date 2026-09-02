@props(['initialTenureMonths', 'subsequentTenureMonths'])

{{--
    "How your repayment changes" visual for a hybrid/flexi term loan. Values
    are always passed in from a real LoanProduct/LenderProduct configuration
    by the caller — this component never invents a tenure split itself, and
    renders an honest "varies by lender" state when either figure is missing.
--}}
<div {{ $attributes->class('mt-12') }} data-reveal="up">
    <h2 class="font-display text-xl font-semibold text-ink">How your repayment changes</h2>

    @if ($initialTenureMonths && $subsequentTenureMonths)
        <div class="mt-6 grid gap-4 sm:grid-cols-[1fr_auto_1fr_auto_1fr] sm:items-center">
            <div class="rounded-2xl border border-line bg-surface-2 p-5 text-center">
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-accent">Initial tenure</p>
                <p class="mt-1 font-display text-lg font-semibold text-ink">Months 1–{{ $initialTenureMonths }}</p>
                <p class="mt-1 text-sm text-ink-muted">Interest-only repayment</p>
            </div>
            <div class="flex justify-center text-ink-faint" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5 rotate-90 sm:rotate-0"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10h12m0 0-4-4m4 4-4 4" /></svg>
            </div>
            <div class="rounded-2xl border border-line bg-surface-2 p-5 text-center">
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-accent">Subsequent tenure</p>
                <p class="mt-1 font-display text-lg font-semibold text-ink">Months {{ $initialTenureMonths + 1 }}–{{ $initialTenureMonths + $subsequentTenureMonths }}</p>
                <p class="mt-1 text-sm text-ink-muted">Principal + interest</p>
            </div>
            <div class="flex justify-center text-ink-faint" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5 rotate-90 sm:rotate-0"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10h12m0 0-4-4m4 4-4 4" /></svg>
            </div>
            <div class="rounded-2xl border border-pass/40 bg-pass-soft p-5 text-center">
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-pass">Loan closure</p>
                <p class="mt-1 font-display text-lg font-semibold text-ink">Month {{ $initialTenureMonths + $subsequentTenureMonths }}</p>
                <p class="mt-1 text-sm text-ink-muted">Fully repaid</p>
            </div>
        </div>
        <p class="mt-3 text-xs text-ink-faint">Illustrative split shown for the default tenure — exact initial and subsequent tenure terms are lender-specific and shown per lender in the comparison and calculator above.</p>
    @else
        <p class="mt-4 text-sm text-ink-muted">The exact initial-tenure split varies by lender — select a lender in the calculator above to see its specific initial and subsequent tenure.</p>
    @endif
</div>
