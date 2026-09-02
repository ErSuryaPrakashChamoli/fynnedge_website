<?php

use App\Support\Calculators\GstCalculator;
use App\Support\Formatting\IndianNumberFormatter;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public float $amount = 10000;

    public float $rate = 18.00;

    public string $mode = 'add';

    /**
     * @return array<int, float>
     */
    public function rateSlabs(): array
    {
        return [0.25, 3.00, 5.00, 12.00, 18.00, 28.00];
    }

    /**
     * @return array{base_amount: float, cgst: float, sgst: float, gst_amount: float, total_amount: float}
     */
    #[Computed]
    public function result(): array
    {
        return $this->mode === 'remove'
            ? GstCalculator::removeGst($this->amount, $this->rate)
            : GstCalculator::addGst($this->amount, $this->rate);
    }

    public function selectMode(string $mode): void
    {
        $this->mode = in_array($mode, ['add', 'remove'], true) ? $mode : 'add';
    }

    public function formatAmount(int|float $amount): string
    {
        return IndianNumberFormatter::format($amount);
    }

    public function updated(string $property): void
    {
        if ($property !== 'amount') {
            return;
        }

        if ($this->amount < 0) {
            $this->amount = 0.0;
            $this->addError('amount', 'The amount can\'t be negative.');

            return;
        }

        $this->resetErrorBag('amount');
    }
};
?>

<div>
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="GST direction">
        <button
            type="button"
            role="tab"
            aria-selected="{{ $mode === 'add' ? 'true' : 'false' }}"
            wire:click="selectMode('add')"
            @class([
                'rounded-full px-4 py-2 text-sm font-medium transition-colors',
                'bg-accent text-white' => $mode === 'add',
                'bg-surface-2 text-ink-muted hover:text-ink' => $mode !== 'add',
            ])
        >
            Add GST
        </button>
        <button
            type="button"
            role="tab"
            aria-selected="{{ $mode === 'remove' ? 'true' : 'false' }}"
            wire:click="selectMode('remove')"
            @class([
                'rounded-full px-4 py-2 text-sm font-medium transition-colors',
                'bg-accent text-white' => $mode === 'remove',
                'bg-surface-2 text-ink-muted hover:text-ink' => $mode !== 'remove',
            ])
        >
            Remove GST
        </button>
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-2">
        <div class="flex flex-col gap-6">
            <div>
                <label for="amount" class="text-sm font-medium text-ink">
                    {{ $mode === 'remove' ? 'Amount (GST inclusive)' : 'Amount (GST exclusive)' }}
                </label>
                <div class="mt-2 flex items-center gap-1 font-mono text-sm text-ink-muted">
                    ₹
                    <input
                        id="amount"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        wire:model.live.debounce.400ms="amount"
                        wire:ignore.self
                        x-effect="const v = $wire.amount; if (document.activeElement !== $el) $el.value = formatIndianNumber(String(v ?? ''))"
                        x-on:focus="$el.value = $el.value.replace(/[^0-9]/g, '')"
                        x-on:blur="$el.value = formatIndianNumber($el.value.replace(/[^0-9]/g, ''))"
                        class="w-full rounded-md border border-line-strong bg-surface px-2 py-1.5 text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                    >
                </div>
                <p class="mt-1 text-xs text-ink-faint" x-text="numberToIndianWords(String($wire.amount)) + ' Rupees only'"></p>
                @error('amount') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-sm font-medium text-ink">GST rate</label>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($this->rateSlabs() as $slab)
                        <button
                            type="button"
                            wire:click="$set('rate', {{ $slab }})"
                            @class([
                                'rounded-md border px-3 py-1.5 text-sm font-medium transition-colors',
                                'border-accent bg-accent-soft text-accent-strong' => (float) $rate === $slab,
                                'border-line-strong text-ink-muted hover:text-ink' => (float) $rate !== $slab,
                            ])
                        >
                            {{ rtrim(rtrim(number_format($slab, 2), '0'), '.') }}%
                        </button>
                    @endforeach
                </div>
                <div class="mt-2 flex items-center gap-1 font-mono text-sm text-ink-muted">
                    <input
                        id="rate"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        max="100"
                        step="0.01"
                        wire:model.live.debounce.400ms="rate"
                        aria-label="Custom GST rate"
                        class="w-20 rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                    >
                    % (or enter a custom rate)
                </div>
            </div>
        </div>

        <div class="flex flex-col justify-center gap-5 rounded-2xl border border-line bg-surface-2 p-7">
            <div>
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">
                    {{ $mode === 'remove' ? 'Base amount (before GST)' : 'Total amount (with GST)' }}
                </p>
                <p class="mt-1 font-display text-4xl font-semibold text-accent">
                    ₹{{ $this->formatAmount($mode === 'remove' ? $this->result['base_amount'] : $this->result['total_amount']) }}
                </p>
            </div>
            <div class="grid grid-cols-3 gap-4 border-t border-line pt-5">
                <div>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">CGST</p>
                    <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['cgst']) }}</p>
                </div>
                <div>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">SGST</p>
                    <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['sgst']) }}</p>
                </div>
                <div>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total GST</p>
                    <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['gst_amount']) }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
