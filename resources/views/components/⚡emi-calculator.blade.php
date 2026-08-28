<?php

use App\Support\Calculators\EmiCalculator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public float $principal = 500000;

    public float $annualRate = 10.5;

    public int $tenureMonths = 36;

    #[Computed]
    public function result(): array
    {
        return EmiCalculator::calculate($this->principal, $this->annualRate, $this->tenureMonths);
    }

    public function rules(): array
    {
        return [
            'principal' => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'annualRate' => ['required', 'numeric', 'min:0', 'max:36'],
            'tenureMonths' => ['required', 'integer', 'min:1', 'max:360'],
        ];
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }
};
?>

<div class="grid gap-8 lg:grid-cols-2">
    <div class="flex flex-col gap-6">
        <div>
            <div class="flex items-baseline justify-between">
                <label for="principal" class="text-sm font-medium text-ink">Loan amount</label>
                <span class="font-mono text-sm text-ink-muted">₹{{ number_format($principal) }}</span>
            </div>
            <input
                id="principal"
                type="range"
                min="10000"
                max="10000000"
                step="10000"
                wire:model.live="principal"
                class="mt-2 w-full accent-accent"
            >
            @error('principal') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <label for="annualRate" class="text-sm font-medium text-ink">Interest rate (p.a.)</label>
                <span class="font-mono text-sm text-ink-muted">{{ number_format($annualRate, 1) }}%</span>
            </div>
            <input
                id="annualRate"
                type="range"
                min="0"
                max="30"
                step="0.1"
                wire:model.live="annualRate"
                class="mt-2 w-full accent-accent"
            >
            @error('annualRate') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <label for="tenureMonths" class="text-sm font-medium text-ink">Tenure</label>
                <span class="font-mono text-sm text-ink-muted">{{ $tenureMonths }} months</span>
            </div>
            <input
                id="tenureMonths"
                type="range"
                min="3"
                max="240"
                step="1"
                wire:model.live="tenureMonths"
                class="mt-2 w-full accent-accent"
            >
            @error('tenureMonths') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex flex-col justify-center gap-5 rounded-2xl border border-line bg-surface-2 p-7">
        <div>
            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Monthly EMI</p>
            <p class="mt-1 font-display text-4xl font-semibold text-accent">₹{{ number_format($this->result['emi']) }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4 border-t border-line pt-5">
            <div>
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total interest</p>
                <p class="mt-1 text-lg font-medium text-ink">₹{{ number_format($this->result['total_interest']) }}</p>
            </div>
            <div>
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total payment</p>
                <p class="mt-1 text-lg font-medium text-ink">₹{{ number_format($this->result['total_payment']) }}</p>
            </div>
        </div>
        <p class="text-xs text-ink-faint">Indicative only — your actual EMI depends on the lender's exact rate and terms at sanction.</p>
    </div>
</div>
