<?php

use App\Enums\LenderStatus;
use App\Enums\LoanCategory;
use App\Models\LenderProduct;
use App\Support\Calculators\EmiCalculator;
use App\Support\Calculators\LoanCalculatorPreset;
use App\Support\Formatting\IndianNumberFormatter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Dedicated calculator for the Flexi Hybrid Term Loan's two-stage repayment
 * (interest-only initial tenure, then principal + interest for the rest).
 * Kept separate from ⚡emi-calculator.blade.php rather than branching inside
 * it — that component is shared by 11 other, single-tenure loan categories
 * and is explicitly documented as needing no changes to add an ordinary EMI
 * product; retrofitting a lender selector and a second result stage into it
 * would risk regressing every other loan type. This component reuses the
 * same calculator engine (EmiCalculator) and the same LoanCalculatorPreset
 * config source instead of duplicating either.
 */
new class extends Component
{
    public float $principal;

    public float $annualRate;

    public int $totalTenureMonths;

    public ?int $lenderProductId = null;

    public function mount(): void
    {
        $preset = $this->preset;

        $this->principal = (float) $preset['default_amount'];
        $this->annualRate = $preset['default_rate'];
        $this->totalTenureMonths = max($preset['min_years'] * 12, min($preset['max_years'] * 12, $preset['default_years'] * 12));

        $firstLender = $this->lenderOptions->first();
        $this->lenderProductId = $firstLender?->id;
    }

    /**
     * @return array{label: string, min_amount: float, max_amount: float, default_amount: float, min_rate: float, max_rate: float, default_rate: float, min_years: int, max_years: int, default_years: int, rate_note: ?string, is_hybrid: bool, default_initial_tenure_months: ?int}
     */
    #[Computed]
    public function preset(): array
    {
        return LoanCalculatorPreset::for(LoanCategory::FlexiHybridTermLoan) ?? [
            'label' => LoanCategory::FlexiHybridTermLoan->getLabel(),
            'min_amount' => 100_000.0, 'max_amount' => 20_000_000.0, 'default_amount' => 1_000_000.0,
            'min_rate' => 10.0, 'max_rate' => 18.0, 'default_rate' => 10.0,
            'min_years' => 2, 'max_years' => 6, 'default_years' => 5,
            'rate_note' => null, 'is_hybrid' => true, 'default_initial_tenure_months' => 12,
        ];
    }

    /**
     * Every active lender offer on the Flexi Hybrid Term Loan product —
     * the same relation the lender comparison table and product page use,
     * so this list never drifts from what's shown elsewhere on the page.
     *
     * @return \Illuminate\Support\Collection<int, LenderProduct>
     */
    #[Computed]
    public function lenderOptions(): \Illuminate\Support\Collection
    {
        $product = LoanCalculatorPreset::productFor(LoanCategory::FlexiHybridTermLoan);

        if (! $product) {
            return collect();
        }

        return $product->lenderProducts()
            ->where('status', LenderStatus::Active)
            ->with('lender')
            ->get();
    }

    #[Computed]
    public function selectedLenderProduct(): ?LenderProduct
    {
        if ($this->lenderProductId === null) {
            return null;
        }

        return $this->lenderOptions->firstWhere('id', $this->lenderProductId);
    }

    /**
     * The lender's own configured initial tenure if selected, else the
     * product-level default. Null means there is genuinely nothing
     * configured to run the calculator against.
     */
    #[Computed]
    public function initialTenureMonths(): ?int
    {
        return $this->selectedLenderProduct?->effectiveInitialTenureMonths()
            ?? $this->preset['default_initial_tenure_months'];
    }

    #[Computed]
    public function subsequentTenureMonths(): ?int
    {
        $initial = $this->initialTenureMonths;

        if ($initial === null || $this->totalTenureMonths <= $initial) {
            return null;
        }

        return $this->totalTenureMonths - $initial;
    }

    /**
     * @return array{initial_emi: float, subsequent_emi: float, initial_tenure_months: int, subsequent_tenure_months: int, total_interest: float, total_repayment: float, total_tenure_months: int}|null
     */
    #[Computed]
    public function result(): ?array
    {
        $initial = $this->initialTenureMonths;
        $subsequent = $this->subsequentTenureMonths;

        if ($initial === null || $subsequent === null) {
            return null;
        }

        return EmiCalculator::calculateHybrid($this->principal, $this->annualRate, $initial, $subsequent);
    }

    /**
     * The full month-by-month schedule for the selected result, grouped by
     * year for the "full breakdown" table — empty when result() is null.
     *
     * @return array<int, array<int, array{month: int, year: int, month_in_year: int, stage: string, principal_paid: float, interest_paid: float, total_paid: float, balance: float}>>
     */
    #[Computed]
    public function monthsByYear(): array
    {
        if (! $this->result) {
            return [];
        }

        $months = EmiCalculator::hybridMonthlySchedule(
            $this->principal,
            $this->annualRate,
            $this->result['initial_tenure_months'],
            $this->result['subsequent_tenure_months'],
        );

        $byYear = [];
        foreach ($months as $row) {
            $byYear[$row['year']][] = $row;
        }

        return $byYear;
    }

    /**
     * @return array<int, array{year: int, principal_paid: float, interest_paid: float, total_paid: float, balance: float}>
     */
    #[Computed]
    public function yearlySchedule(): array
    {
        if (! $this->result) {
            return [];
        }

        return EmiCalculator::hybridYearlySchedule(
            $this->principal,
            $this->annualRate,
            $this->result['initial_tenure_months'],
            $this->result['subsequent_tenure_months'],
        );
    }

    /**
     * Every active lender's own computed hybrid result for the *current*
     * loan amount and total tenure — so entering an amount once lets a
     * visitor compare all lenders side by side, each using its own rate and
     * initial tenure rather than the currently-selected one. A lender
     * without enough of its own configuration (no rate, or no initial
     * tenure resolvable) reports result: null so the view can render its
     * honest "Available on request" state instead of silently borrowing
     * another lender's figures.
     *
     * @return array<int, array{offer: LenderProduct, result: array|null}>
     */
    #[Computed]
    public function lenderComparison(): array
    {
        return $this->lenderOptions->map(function (LenderProduct $offer) {
            $rate = $offer->interest_rate_from !== null ? (float) $offer->interest_rate_from : null;
            $initial = $offer->effectiveInitialTenureMonths();
            $subsequent = $initial !== null ? $offer->subsequentTenureMonths($this->totalTenureMonths) : null;

            $result = ($rate !== null && $initial !== null && $subsequent !== null)
                ? EmiCalculator::calculateHybrid($this->principal, $rate, $initial, $subsequent)
                : null;

            return ['offer' => $offer, 'result' => $result];
        })->all();
    }

    /**
     * A standard, single-tenure EMI (no interest-only stage) at the same
     * amount, rate and total tenure currently entered — the comparator for
     * the "Flexi Hybrid vs a conventional loan" chart. Uses the same
     * annualRate the visitor already entered rather than inventing a
     * separate "personal loan rate", since this is illustrating the
     * structural difference in repayment shape, not quoting a real personal
     * loan product.
     *
     * @return array{emi: float, total_payment: float, total_interest: float}
     */
    #[Computed]
    public function conventionalLoanComparison(): array
    {
        return EmiCalculator::calculate($this->principal, $this->annualRate, $this->totalTenureMonths);
    }

    public function monthDate(int $absoluteMonth): \Illuminate\Support\Carbon
    {
        return now()->startOfMonth()->addMonths($absoluteMonth - 1);
    }

    /**
     * @param  array<int, array{month: int}>  $monthsInYear
     */
    public function yearLabel(array $monthsInYear): string
    {
        $first = $this->monthDate($monthsInYear[0]['month']);
        $last = $this->monthDate(end($monthsInYear)['month']);

        return $first->isSameMonth($last) ? $first->format('M Y') : "{$first->format('M Y')} – {$last->format('M Y')}";
    }

    public function selectLender(?int $lenderProductId): void
    {
        $this->lenderProductId = $lenderProductId;

        $offer = $this->selectedLenderProduct;

        if ($offer?->interest_rate_from !== null) {
            $this->annualRate = (float) $offer->interest_rate_from;
        }

        if ($offer?->max_amount !== null) {
            $this->principal = min($this->principal, (float) $offer->max_amount);
        }

        if ($offer?->min_amount !== null) {
            $this->principal = max($this->principal, (float) $offer->min_amount);
        }
    }

    /**
     * Same server-side clamp-and-validate backstop as ⚡emi-calculator's
     * updated() — every value round-trips through here regardless of
     * whether it arrived via slider, typed input, or a direct component
     * request.
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['principal', 'annualRate', 'totalTenureMonths'], true)) {
            return;
        }

        $preset = $this->preset;
        [$min, $max, $label] = match ($property) {
            'principal' => [$preset['min_amount'], $preset['max_amount'], 'loan amount'],
            'annualRate' => [$preset['min_rate'], $preset['max_rate'], 'interest rate'],
            'totalTenureMonths' => [$preset['min_years'] * 12, $preset['max_years'] * 12, 'total tenure'],
        };

        $value = $this->{$property};

        if ($value < $min || $value > $max) {
            $clamped = max($min, min($max, $value));
            $this->{$property} = $property === 'totalTenureMonths' ? (int) $clamped : (float) $clamped;
            $this->addError($property, "Adjusted the {$label} to stay within {$preset['label']}'s allowed range.");

            return;
        }

        $this->resetErrorBag($property);
    }

    public function formatAmount(int|float $amount): string
    {
        return IndianNumberFormatter::format($amount);
    }
};
?>

<div>
    @if ($this->lenderOptions->isNotEmpty())
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Lender">
            <button
                type="button"
                role="tab"
                aria-selected="{{ $lenderProductId === null ? 'true' : 'false' }}"
                wire:click="selectLender(null)"
                @class([
                    'rounded-full px-4 py-2 text-sm font-medium transition-colors',
                    'bg-accent text-white' => $lenderProductId === null,
                    'bg-surface-2 text-ink-muted hover:text-ink' => $lenderProductId !== null,
                ])
            >
                Generic estimate
            </button>
            @foreach ($this->lenderOptions as $offer)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $lenderProductId === $offer->id ? 'true' : 'false' }}"
                    wire:click="selectLender({{ $offer->id }})"
                    @class([
                        'rounded-full px-4 py-2 text-sm font-medium transition-colors',
                        'bg-accent text-white' => $lenderProductId === $offer->id,
                        'bg-surface-2 text-ink-muted hover:text-ink' => $lenderProductId !== $offer->id,
                    ])
                >
                    {{ $offer->lender->name }}
                </button>
            @endforeach
        </div>
    @endif

    <div class="mt-8 grid gap-8 lg:grid-cols-2">
        <div class="flex flex-col gap-6">
            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="fh-principal" class="text-sm font-medium text-ink">Loan amount</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        ₹
                        <input
                            id="fh-principal"
                            type="number"
                            inputmode="numeric"
                            min="{{ $this->preset['min_amount'] }}"
                            max="{{ $this->preset['max_amount'] }}"
                            wire:model.live.debounce.400ms="principal"
                            class="w-28 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Loan amount"
                    min="{{ $this->preset['min_amount'] }}"
                    max="{{ $this->preset['max_amount'] }}"
                    step="{{ max((int) (($this->preset['max_amount'] - $this->preset['min_amount']) / 200), 1000) }}"
                    wire:model.live="principal"
                    class="mt-2 w-full accent-accent"
                >
                @error('principal') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="fh-rate" class="text-sm font-medium text-ink">Interest rate (p.a.)</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        <input
                            id="fh-rate"
                            type="number"
                            inputmode="decimal"
                            min="{{ $this->preset['min_rate'] }}"
                            max="{{ $this->preset['max_rate'] }}"
                            step="0.01"
                            wire:model.live.debounce.400ms="annualRate"
                            class="w-20 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                        %
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Interest rate"
                    min="{{ $this->preset['min_rate'] }}"
                    max="{{ $this->preset['max_rate'] }}"
                    step="0.1"
                    wire:model.live="annualRate"
                    class="mt-2 w-full accent-accent"
                >
                @error('annualRate') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="fh-tenure" class="text-sm font-medium text-ink">Total tenure</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        <input
                            id="fh-tenure"
                            type="number"
                            inputmode="numeric"
                            min="{{ $this->preset['min_years'] * 12 }}"
                            max="{{ $this->preset['max_years'] * 12 }}"
                            step="12"
                            wire:model.live.debounce.400ms="totalTenureMonths"
                            class="w-16 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                        months
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Total tenure in months"
                    min="{{ $this->preset['min_years'] * 12 }}"
                    max="{{ $this->preset['max_years'] * 12 }}"
                    step="12"
                    wire:model.live="totalTenureMonths"
                    class="mt-2 w-full accent-accent"
                >
                @error('totalTenureMonths') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex flex-col justify-center gap-5 rounded-2xl border border-line bg-surface-2 p-7">
            @if ($this->result)
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">
                            Initial EMI ({{ $this->result['initial_tenure_months'] }} mo)
                        </p>
                        <p class="mt-1 font-display text-2xl font-semibold text-accent">₹{{ $this->formatAmount($this->result['initial_emi']) }}</p>
                        <p class="mt-0.5 text-[0.65rem] text-ink-faint">Interest-only</p>
                    </div>
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">
                            Subsequent EMI ({{ $this->result['subsequent_tenure_months'] }} mo)
                        </p>
                        <p class="mt-1 font-display text-2xl font-semibold text-ink">₹{{ $this->formatAmount($this->result['subsequent_emi']) }}</p>
                        <p class="mt-0.5 text-[0.65rem] text-ink-faint">Principal + interest</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 border-t border-line pt-5">
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total interest</p>
                        <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['total_interest']) }}</p>
                    </div>
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total repayment</p>
                        <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['total_repayment']) }}</p>
                    </div>
                </div>
                <p class="text-xs text-ink-faint">Indicative only — your actual EMI and initial/subsequent split depend on the lender's exact terms at sanction.</p>
            @else
                <p class="text-sm text-ink-muted">
                    @if ($this->lenderProductId !== null)
                        {{ $this->selectedLenderProduct?->lender?->name }} hasn't published its initial-tenure terms for this product yet — available on request.
                    @else
                        Select a lender above, or increase the total tenure beyond the initial tenure, to see an estimate.
                    @endif
                </p>
            @endif
        </div>
    </div>

    {{-- Compare all lenders live, at the amount/tenure entered above --}}
    @if ($this->lenderOptions->isNotEmpty())
        <div class="mt-10">
            <p class="font-display text-lg font-semibold text-ink">Compare all lenders at this amount &amp; tenure</p>
            <p class="mt-1 text-xs text-ink-faint">Each row uses that lender's own rate and initial tenure, for the ₹{{ $this->formatAmount($principal) }} amount and {{ $totalTenureMonths }}-month total tenure entered above.</p>
            <div class="mt-4 overflow-x-auto rounded-2xl border border-line">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-line bg-surface-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                            <th scope="col" class="px-4 py-3">Lender</th>
                            <th scope="col" class="px-4 py-3">Rate</th>
                            <th scope="col" class="px-4 py-3">Initial EMI</th>
                            <th scope="col" class="px-4 py-3">Subsequent EMI</th>
                            <th scope="col" class="px-4 py-3">Total interest</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Select</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->lenderComparison as $row)
                            <tr @class(['bg-accent-soft' => $lenderProductId === $row['offer']->id])>
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">{{ $row['offer']->lender->name }}</td>
                                @if ($row['result'])
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">{{ $row['offer']->interest_rate_from }}%</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">₹{{ $this->formatAmount($row['result']['initial_emi']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">₹{{ $this->formatAmount($row['result']['subsequent_emi']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-muted">₹{{ $this->formatAmount($row['result']['total_interest']) }}</td>
                                @else
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-ink-faint" colspan="4">Available on request</td>
                                @endif
                                <td class="whitespace-nowrap px-4 py-3">
                                    <button type="button" wire:click="selectLender({{ $row['offer']->id }})" class="text-sm font-medium text-accent hover:underline">
                                        {{ $lenderProductId === $row['offer']->id ? 'Selected' : 'Select' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Flexi Hybrid vs a conventional (single-tenure) loan, at the same amount/rate/tenure --}}
    <div class="mt-10">
        <p class="font-display text-lg font-semibold text-ink">Flexi Hybrid vs a conventional loan</p>
        <p class="mt-1 text-xs text-ink-faint">Same ₹{{ $this->formatAmount($principal) }} amount, {{ $annualRate }}% rate and {{ $totalTenureMonths }}-month tenure — a conventional loan charges one flat EMI throughout; Flexi Hybrid starts lower, then rises.</p>

        @php
            $conventional = $this->conventionalLoanComparison;
            $maxMonthly = max($conventional['emi'], $this->result['initial_emi'] ?? 0, $this->result['subsequent_emi'] ?? 0, 1);
        @endphp

        <div class="mt-4 flex flex-col gap-4 rounded-2xl border border-line p-5">
            <div class="grid grid-cols-[7rem_1fr_auto] items-center gap-3 sm:grid-cols-[9rem_1fr_auto]">
                <span class="text-xs font-medium text-ink-muted">Conventional EMI</span>
                <div class="h-5 rounded bg-surface-2">
                    <div class="h-5 rounded bg-ink-faint" style="width: {{ $conventional['emi'] / $maxMonthly * 100 }}%"></div>
                </div>
                <span class="font-mono text-xs text-ink">₹{{ $this->formatAmount($conventional['emi']) }}/mo</span>
            </div>

            @if ($this->result)
                <div class="grid grid-cols-[7rem_1fr_auto] items-center gap-3 sm:grid-cols-[9rem_1fr_auto]">
                    <span class="text-xs font-medium text-ink-muted">Flexi Hybrid — initial</span>
                    <div class="h-5 rounded bg-surface-2">
                        <div class="h-5 rounded bg-accent" style="width: {{ $this->result['initial_emi'] / $maxMonthly * 100 }}%"></div>
                    </div>
                    <span class="font-mono text-xs text-ink">₹{{ $this->formatAmount($this->result['initial_emi']) }}/mo</span>
                </div>
                <div class="grid grid-cols-[7rem_1fr_auto] items-center gap-3 sm:grid-cols-[9rem_1fr_auto]">
                    <span class="text-xs font-medium text-ink-muted">Flexi Hybrid — subsequent</span>
                    <div class="h-5 rounded bg-surface-2">
                        <div class="h-5 rounded bg-accent-strong" style="width: {{ $this->result['subsequent_emi'] / $maxMonthly * 100 }}%"></div>
                    </div>
                    <span class="font-mono text-xs text-ink">₹{{ $this->formatAmount($this->result['subsequent_emi']) }}/mo</span>
                </div>

                <div class="grid grid-cols-2 gap-4 border-t border-line pt-4 text-sm">
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total interest — conventional</p>
                        <p class="mt-1 font-medium text-ink">₹{{ $this->formatAmount($conventional['total_interest']) }}</p>
                    </div>
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total interest — Flexi Hybrid</p>
                        <p class="mt-1 font-medium text-ink">₹{{ $this->formatAmount($this->result['total_interest']) }}</p>
                    </div>
                </div>
                <p class="text-xs text-ink-faint">
                    Flexi Hybrid keeps your outflow lower for the first {{ $this->result['initial_tenure_months'] }} months, then it rises above a conventional EMI for the rest of the tenure.
                    @if ($this->result['total_interest'] > $conventional['total_interest'])
                        Because less principal is repaid early on, total interest over the full tenure is higher than a conventional loan of the same amount, rate and tenure — the trade-off for lower initial outflow.
                    @else
                        Total interest over the full tenure is not higher than a conventional loan of the same amount, rate and tenure.
                    @endif
                </p>
            @endif
        </div>
    </div>

    {{-- Full month-by-month breakdown across the entire tenure --}}
    @if ($this->result)
        <div class="mt-10">
            <p class="font-display text-lg font-semibold text-ink">Full breakdown, starting this month</p>
            <p class="mt-1 text-xs text-ink-faint">Assumes your first payment falls this month — click a period to see every month within it. The initial (interest-only) period is marked separately from the subsequent (principal + interest) period.</p>
            <div class="mt-4 rounded-xl border border-line">
                <div class="hidden border-b border-line bg-surface-2 px-4 py-2.5 text-left text-xs font-medium text-ink-muted sm:grid sm:grid-cols-[2rem_1fr_1fr_1fr_1fr_1fr]">
                    <span></span>
                    <span>Period</span>
                    <span>Stage</span>
                    <span class="text-right">Principal paid</span>
                    <span class="text-right">Interest paid</span>
                    <span class="text-right">Total paid</span>
                </div>
                @foreach ($this->yearlySchedule as $row)
                    @php
                        $yearStages = collect($this->monthsByYear[$row['year']])->pluck('stage')->unique();
                        $yearStageLabel = $yearStages->count() > 1 ? 'Initial → Subsequent' : ($yearStages->first() === 'initial' ? 'Initial' : 'Subsequent');
                    @endphp
                    <details class="group border-b border-line last:border-0 odd:bg-surface even:bg-surface-2/50">
                        <summary class="grid cursor-pointer list-none grid-cols-2 items-center gap-1 px-4 py-3 text-sm hover:bg-surface-2 sm:grid-cols-[2rem_1fr_1fr_1fr_1fr_1fr]">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 text-ink-faint transition-transform group-open:rotate-90"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4l6 6-6 6" /></svg>
                            <span class="font-medium text-ink">{{ $this->yearLabel($this->monthsByYear[$row['year']]) }}</span>
                            <span class="text-xs text-ink-faint">{{ $yearStageLabel }}</span>
                            <span class="text-right font-mono text-ink sm:text-right">₹{{ $this->formatAmount($row['principal_paid']) }}</span>
                            <span class="text-right font-mono text-ink sm:text-right">₹{{ $this->formatAmount($row['interest_paid']) }}</span>
                            <span class="col-span-2 text-right font-mono text-ink-muted sm:col-span-1">₹{{ $this->formatAmount($row['total_paid']) }}</span>
                        </summary>

                        <div class="overflow-x-auto border-t border-line bg-surface px-4 py-3">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-ink-faint">
                                        <th class="py-1.5 pr-3 font-medium">Month</th>
                                        <th class="py-1.5 pr-3 font-medium">Stage</th>
                                        <th class="py-1.5 pr-3 text-right font-medium">Principal paid</th>
                                        <th class="py-1.5 pr-3 text-right font-medium">Interest paid</th>
                                        <th class="py-1.5 pr-3 text-right font-medium">Total paid</th>
                                        <th class="py-1.5 text-right font-medium">Balance remaining</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($this->monthsByYear[$row['year']] as $monthRow)
                                        <tr class="border-t border-line/60">
                                            <td class="py-1.5 pr-3 text-ink-muted">{{ $this->monthDate($monthRow['month'])->format('M Y') }}</td>
                                            <td class="py-1.5 pr-3">
                                                <x-ui.badge :tone="$monthRow['stage'] === 'initial' ? 'accent' : 'muted'">{{ ucfirst($monthRow['stage']) }}</x-ui.badge>
                                            </td>
                                            <td class="py-1.5 pr-3 text-right font-mono text-ink">₹{{ $this->formatAmount($monthRow['principal_paid']) }}</td>
                                            <td class="py-1.5 pr-3 text-right font-mono text-ink">₹{{ $this->formatAmount($monthRow['interest_paid']) }}</td>
                                            <td class="py-1.5 pr-3 text-right font-mono text-ink-muted">₹{{ $this->formatAmount($monthRow['total_paid']) }}</td>
                                            <td class="py-1.5 text-right font-mono text-ink-muted">₹{{ $this->formatAmount($monthRow['balance']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach

                <div class="border-t border-line bg-surface-2 px-4 py-2.5 text-xs text-ink-faint">
                    Balance remaining after the last month of each period shown above.
                </div>
            </div>
        </div>
    @endif
</div>
