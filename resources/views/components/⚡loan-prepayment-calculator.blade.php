<?php

use App\Enums\LoanCategory;
use App\Support\Calculators\LoanCalculatorPreset;
use App\Support\Calculators\PrepaymentCalculator;
use App\Support\Formatting\IndianNumberFormatter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $category;

    public float $outstandingPrincipal;

    public float $annualRate;

    public int $remainingTenureYears;

    public float $prepaymentAmount;

    public string $mode = 'reduce_tenure';

    public function mount(string $category): void
    {
        $category = LoanCategory::tryFrom($category);
        $category = ($category && LoanCalculatorPreset::for($category)) ? $category : LoanCategory::PersonalLoan;

        $this->category = $category->value;

        $preset = $this->preset;
        $this->outstandingPrincipal = $preset['default_amount'];
        $this->annualRate = $preset['default_rate'];
        $this->remainingTenureYears = $preset['default_years'];
        $this->prepaymentAmount = round($preset['default_amount'] * 0.1, -3);
    }

    #[Computed]
    public function preset(): array
    {
        return LoanCalculatorPreset::for(LoanCategory::from($this->category));
    }

    /**
     * @return array{original_emi: float, new_emi: float, original_tenure_months: int, new_tenure_months: int, tenure_reduced_months: int, original_total_interest: float, new_total_interest: float, interest_saved: float}
     */
    #[Computed]
    public function result(): array
    {
        return PrepaymentCalculator::calculate(
            $this->outstandingPrincipal,
            $this->annualRate,
            $this->remainingTenureYears * 12,
            $this->prepaymentAmount,
            $this->mode,
        );
    }

    public function selectMode(string $mode): void
    {
        $this->mode = in_array($mode, ['reduce_tenure', 'reduce_emi'], true) ? $mode : 'reduce_tenure';
    }

    public function formatAmount(int|float $amount): string
    {
        return IndianNumberFormatter::format($amount);
    }

    public function formatYearsAndMonths(int $months): string
    {
        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;

        $parts = [];
        if ($years > 0) {
            $parts[] = $years.' '.Str::plural('year', $years);
        }
        if ($remainingMonths > 0 || $parts === []) {
            $parts[] = $remainingMonths.' '.Str::plural('month', $remainingMonths);
        }

        return implode(' ', $parts);
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['outstandingPrincipal', 'annualRate', 'remainingTenureYears', 'prepaymentAmount'], true)) {
            return;
        }

        $preset = $this->preset;

        if ($property === 'prepaymentAmount') {
            $value = $this->prepaymentAmount;
            $max = $this->outstandingPrincipal;

            if ($value < 0 || $value > $max) {
                $this->prepaymentAmount = max(0.0, min($max, $value));
                $this->addError('prepaymentAmount', 'Adjusted the prepayment amount to stay within the outstanding principal.');

                return;
            }

            $this->resetErrorBag('prepaymentAmount');

            return;
        }

        [$min, $max, $label] = match ($property) {
            'outstandingPrincipal' => [$preset['min_amount'], $preset['max_amount'], 'outstanding principal'],
            'annualRate' => [$preset['min_rate'], $preset['max_rate'], 'interest rate'],
            'remainingTenureYears' => [$preset['min_years'], $preset['max_years'], 'remaining tenure'],
        };

        $value = $this->{$property};

        if ($value < $min || $value > $max) {
            $clamped = max($min, min($max, $value));
            $this->{$property} = $property === 'remainingTenureYears' ? (int) $clamped : (float) $clamped;
            $this->addError($property, "Adjusted the {$label} to stay within {$preset['label']}'s allowed range.");

            return;
        }

        $this->resetErrorBag($property);
    }
};
?>

<div>
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Prepayment goal">
        <button
            type="button"
            role="tab"
            aria-selected="{{ $mode === 'reduce_tenure' ? 'true' : 'false' }}"
            wire:click="selectMode('reduce_tenure')"
            @class([
                'rounded-full px-4 py-2 text-sm font-medium transition-colors',
                'bg-accent text-white' => $mode === 'reduce_tenure',
                'bg-surface-2 text-ink-muted hover:text-ink' => $mode !== 'reduce_tenure',
            ])
        >
            Reduce tenure
        </button>
        <button
            type="button"
            role="tab"
            aria-selected="{{ $mode === 'reduce_emi' ? 'true' : 'false' }}"
            wire:click="selectMode('reduce_emi')"
            @class([
                'rounded-full px-4 py-2 text-sm font-medium transition-colors',
                'bg-accent text-white' => $mode === 'reduce_emi',
                'bg-surface-2 text-ink-muted hover:text-ink' => $mode !== 'reduce_emi',
            ])
        >
            Reduce EMI
        </button>
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-2">
        <div class="flex flex-col gap-6">
            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="outstandingPrincipal" class="text-sm font-medium text-ink">Outstanding principal</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        ₹
                        <input
                            id="outstandingPrincipal"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            wire:model.live.debounce.400ms="outstandingPrincipal"
                            wire:ignore.self
                            x-effect="const v = $wire.outstandingPrincipal; if (document.activeElement !== $el) $el.value = formatIndianNumber(String(v ?? ''))"
                            x-on:focus="$el.value = $el.value.replace(/[^0-9]/g, '')"
                            x-on:blur="$el.value = formatIndianNumber($el.value.replace(/[^0-9]/g, ''))"
                            class="w-28 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Outstanding principal"
                    min="{{ $this->preset['min_amount'] }}"
                    max="{{ $this->preset['max_amount'] }}"
                    step="{{ max((int) (($this->preset['max_amount'] - $this->preset['min_amount']) / 200), 1000) }}"
                    wire:model.live="outstandingPrincipal"
                    class="mt-2 w-full accent-accent"
                >
                <p class="mt-1 text-xs text-ink-faint" x-text="numberToIndianWords(String($wire.outstandingPrincipal)) + ' Rupees only'"></p>
                @error('outstandingPrincipal') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="annualRate" class="text-sm font-medium text-ink">Interest rate (p.a.)</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        <input
                            id="annualRate"
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
                    <label for="remainingTenureYears" class="text-sm font-medium text-ink">Remaining tenure</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        <input
                            id="remainingTenureYears"
                            type="number"
                            inputmode="numeric"
                            min="{{ $this->preset['min_years'] }}"
                            max="{{ $this->preset['max_years'] }}"
                            step="1"
                            wire:model.live.debounce.400ms="remainingTenureYears"
                            class="w-16 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                        {{ Str::plural('year', $remainingTenureYears) }}
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Remaining tenure in years"
                    min="{{ $this->preset['min_years'] }}"
                    max="{{ $this->preset['max_years'] }}"
                    step="1"
                    wire:model.live="remainingTenureYears"
                    class="mt-2 w-full accent-accent"
                >
                @error('remainingTenureYears') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="prepaymentAmount" class="text-sm font-medium text-ink">Prepayment (lumpsum)</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        ₹
                        <input
                            id="prepaymentAmount"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            wire:model.live.debounce.400ms="prepaymentAmount"
                            wire:ignore.self
                            x-effect="const v = $wire.prepaymentAmount; if (document.activeElement !== $el) $el.value = formatIndianNumber(String(v ?? ''))"
                            x-on:focus="$el.value = $el.value.replace(/[^0-9]/g, '')"
                            x-on:blur="$el.value = formatIndianNumber($el.value.replace(/[^0-9]/g, ''))"
                            class="w-28 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Prepayment amount"
                    min="0"
                    max="{{ $outstandingPrincipal }}"
                    step="{{ max((int) ($outstandingPrincipal / 200), 1000) }}"
                    wire:model.live="prepaymentAmount"
                    class="mt-2 w-full accent-accent"
                >
                <p class="mt-1 text-xs text-ink-faint" x-text="numberToIndianWords(String($wire.prepaymentAmount)) + ' Rupees only'"></p>
                @error('prepaymentAmount') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex flex-col justify-center gap-5 rounded-2xl border border-line bg-surface-2 p-7">
            <div>
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Interest saved</p>
                <p class="mt-1 font-display text-4xl font-semibold text-accent">₹{{ $this->formatAmount($this->result['interest_saved']) }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4 border-t border-line pt-5">
                @if ($mode === 'reduce_tenure')
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">New tenure</p>
                        <p class="mt-1 text-lg font-medium text-ink">{{ $this->formatYearsAndMonths($this->result['new_tenure_months']) }}</p>
                    </div>
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Tenure reduced by</p>
                        <p class="mt-1 text-lg font-medium text-ink">{{ $this->formatYearsAndMonths($this->result['tenure_reduced_months']) }}</p>
                    </div>
                @else
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">New EMI</p>
                        <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['new_emi']) }}</p>
                    </div>
                    <div>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Original EMI</p>
                        <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['original_emi']) }}</p>
                    </div>
                @endif
            </div>
            <p class="text-xs text-ink-faint">Indicative only — check with your lender for any prepayment charges before making a lumpsum payment.</p>
        </div>
    </div>
</div>
