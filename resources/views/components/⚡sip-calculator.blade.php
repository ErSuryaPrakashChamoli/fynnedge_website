<?php

use App\Support\Calculators\SipCalculator;
use App\Support\Formatting\IndianNumberFormatter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $frequency;

    public float $installment;

    public float $annualRate;

    public int $tenureYears;

    public function mount(string $frequency = 'monthly'): void
    {
        $this->frequency = in_array($frequency, ['monthly', 'daily'], true) ? $frequency : 'monthly';

        $preset = $this->preset;
        $this->installment = $preset['default_installment'];
        $this->annualRate = $preset['default_rate'];
        $this->tenureYears = $preset['default_years'];
    }

    /**
     * @return array{label: string, min_installment: float, max_installment: float, default_installment: float, min_rate: float, max_rate: float, default_rate: float, min_years: int, max_years: int, default_years: int}
     */
    #[Computed]
    public function preset(): array
    {
        return $this->frequency === 'daily'
            ? [
                'label' => 'daily investment',
                'min_installment' => 50, 'max_installment' => 5000, 'default_installment' => 100,
                'min_rate' => 1.00, 'max_rate' => 20.00, 'default_rate' => 12.00,
                'min_years' => 1, 'max_years' => 10, 'default_years' => 3,
            ]
            : [
                'label' => 'monthly investment',
                'min_installment' => 500, 'max_installment' => 100000, 'default_installment' => 5000,
                'min_rate' => 1.00, 'max_rate' => 20.00, 'default_rate' => 12.00,
                'min_years' => 1, 'max_years' => 30, 'default_years' => 10,
            ];
    }

    /**
     * @return array{invested_amount: float, estimated_returns: float, maturity_value: float}
     */
    #[Computed]
    public function result(): array
    {
        return $this->frequency === 'daily'
            ? SipCalculator::daily($this->installment, $this->annualRate, $this->tenureYears * 365)
            : SipCalculator::monthly($this->installment, $this->annualRate, $this->tenureYears * 12);
    }

    public function formatAmount(int|float $amount): string
    {
        return IndianNumberFormatter::format($amount);
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['installment', 'annualRate', 'tenureYears'], true)) {
            return;
        }

        $preset = $this->preset;
        [$min, $max, $label] = match ($property) {
            'installment' => [$preset['min_installment'], $preset['max_installment'], $preset['label']],
            'annualRate' => [$preset['min_rate'], $preset['max_rate'], 'expected return rate'],
            'tenureYears' => [$preset['min_years'], $preset['max_years'], 'tenure'],
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
                    <label for="installment" class="text-sm font-medium text-ink capitalize">{{ $this->preset['label'] }}</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        ₹
                        <input
                            id="installment"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            wire:model.live.debounce.400ms="installment"
                            wire:ignore.self
                            x-effect="const v = $wire.installment; if (document.activeElement !== $el) $el.value = formatIndianNumber(String(v ?? ''))"
                            x-on:focus="$el.value = $el.value.replace(/[^0-9]/g, '')"
                            x-on:blur="$el.value = formatIndianNumber($el.value.replace(/[^0-9]/g, ''))"
                            class="w-24 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="{{ $this->preset['label'] }}"
                    min="{{ $this->preset['min_installment'] }}"
                    max="{{ $this->preset['max_installment'] }}"
                    step="{{ $frequency === 'daily' ? 10 : 500 }}"
                    wire:model.live="installment"
                    class="mt-2 w-full accent-accent"
                >
                <p class="mt-1 text-xs text-ink-faint" x-text="numberToIndianWords(String($wire.installment)) + ' Rupees only'"></p>
                @error('installment') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3">
                    <label for="annualRate" class="text-sm font-medium text-ink">Expected return (p.a.)</label>
                    <div class="flex items-center gap-1 font-mono text-sm text-ink-muted">
                        <input
                            id="annualRate"
                            type="number"
                            inputmode="decimal"
                            min="{{ $this->preset['min_rate'] }}"
                            max="{{ $this->preset['max_rate'] }}"
                            step="0.5"
                            wire:model.live.debounce.400ms="annualRate"
                            class="w-20 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                        >
                        %
                    </div>
                </div>
                <input
                    type="range"
                    aria-label="Expected return rate"
                    min="{{ $this->preset['min_rate'] }}"
                    max="{{ $this->preset['max_rate'] }}"
                    step="0.5"
                    wire:model.live="annualRate"
                    class="mt-2 w-full accent-accent"
                >
                <p class="mt-1 text-xs text-ink-faint">Mutual fund returns are market-linked and not guaranteed — this is an illustrative rate, not a promised return.</p>
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
                            min="{{ $this->preset['min_years'] }}"
                            max="{{ $this->preset['max_years'] }}"
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
                    min="{{ $this->preset['min_years'] }}"
                    max="{{ $this->preset['max_years'] }}"
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
                    <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['invested_amount']) }}</p>
                </div>
                <div>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Estimated returns</p>
                    <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['estimated_returns']) }}</p>
                </div>
            </div>
            <p class="text-xs text-ink-faint">Indicative only, assuming the same {{ $frequency }} investment throughout the tenure at a constant rate of return.</p>
        </div>
    </div>
</div>
