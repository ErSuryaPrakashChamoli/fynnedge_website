<?php

use App\Support\Calculators\FdCalculator;
use App\Support\Formatting\IndianNumberFormatter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public float $principal = 100000;

    public float $annualRate = 7.00;

    public int $tenureYears = 5;

    private const MIN_AMOUNT = 5000;

    private const MAX_AMOUNT = 10_000_000;

    private const MIN_RATE = 3.00;

    private const MAX_RATE = 9.50;

    private const MIN_YEARS = 1;

    private const MAX_YEARS = 10;

    #[Computed]
    public function result(): array
    {
        return FdCalculator::calculate($this->principal, $this->annualRate, $this->tenureYears * 12);
    }

    public function formatAmount(int|float $amount): string
    {
        return IndianNumberFormatter::format($amount);
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['principal', 'annualRate', 'tenureYears'], true)) {
            return;
        }

        [$min, $max, $label] = match ($property) {
            'principal' => [self::MIN_AMOUNT, self::MAX_AMOUNT, 'deposit amount'],
            'annualRate' => [self::MIN_RATE, self::MAX_RATE, 'interest rate'],
            'tenureYears' => [self::MIN_YEARS, self::MAX_YEARS, 'tenure'],
        };

        $value = $this->{$property};

        if ($value < $min || $value > $max) {
            $clamped = max($min, min($max, $value));
            $this->{$property} = $property === 'tenureYears' ? (int) $clamped : (float) $clamped;
            $this->addError($property, "Adjusted the {$label} to stay within the allowed range.");

            return;
        }

        $this->resetErrorBag($property);
    }
};
?>

<div>
    <div class="grid gap-8 lg:grid-cols-2">
        <div class="flex flex-col gap-6">
            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="principal" class="text-sm font-medium text-ink">Deposit amount</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        ₹
                        <input
                            id="principal"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            wire:model.live.debounce.400ms="principal"
                            wire:ignore.self
                            x-effect="const v = $wire.principal; if (document.activeElement !== $el) $el.value = formatIndianNumber(String(v ?? ''))"
                            x-on:focus="$el.value = $el.value.replace(/[^0-9]/g, '')"
                            x-on:blur="$el.value = formatIndianNumber($el.value.replace(/[^0-9]/g, ''))"
                            class="w-28 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Deposit amount"
                    min="{{ self::MIN_AMOUNT }}"
                    max="{{ self::MAX_AMOUNT }}"
                    step="1000"
                    wire:model.live="principal"
                    class="mt-2 w-full accent-accent"
                >
                <p class="mt-1 text-xs text-ink-faint" x-text="numberToIndianWords(String($wire.principal)) + ' Rupees only'"></p>
                @error('principal') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="annualRate" class="text-sm font-medium text-ink">Interest rate (p.a.)</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        <input
                            id="annualRate"
                            type="number"
                            inputmode="decimal"
                            min="{{ self::MIN_RATE }}"
                            max="{{ self::MAX_RATE }}"
                            step="0.05"
                            wire:model.live.debounce.400ms="annualRate"
                            class="w-20 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                        %
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Interest rate"
                    min="{{ self::MIN_RATE }}"
                    max="{{ self::MAX_RATE }}"
                    step="0.05"
                    wire:model.live="annualRate"
                    class="mt-2 w-full accent-accent"
                >
                @error('annualRate') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="tenureYears" class="text-sm font-medium text-ink">Tenure</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        <input
                            id="tenureYears"
                            type="number"
                            inputmode="numeric"
                            min="{{ self::MIN_YEARS }}"
                            max="{{ self::MAX_YEARS }}"
                            step="1"
                            wire:model.live.debounce.400ms="tenureYears"
                            class="w-16 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                        {{ Str::plural('year', $tenureYears) }}
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Tenure in years"
                    min="{{ self::MIN_YEARS }}"
                    max="{{ self::MAX_YEARS }}"
                    step="1"
                    wire:model.live="tenureYears"
                    class="mt-2 w-full accent-accent"
                >
                @error('tenureYears') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex flex-col justify-center gap-5 rounded-2xl border border-line bg-surface-2 p-7">
            <div>
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Maturity value</p>
                <p class="mt-1 font-display text-4xl font-semibold text-accent">₹{{ $this->formatAmount($this->result['maturity_value']) }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4 border-t border-line pt-5">
                <div>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Invested amount</p>
                    <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->principal) }}</p>
                </div>
                <div>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total interest</p>
                    <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['total_interest']) }}</p>
                </div>
            </div>
            <p class="text-xs text-ink-faint">Assumes quarterly compounding, the standard convention for Indian bank fixed deposits. Actual returns depend on the bank's exact terms.</p>
        </div>
    </div>
</div>
