<?php

use App\Enums\LoanCategory;
use App\Support\Calculators\EmiCalculator;
use App\Support\Calculators\LoanCalculatorPreset;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $category;

    public float $principal;

    public float $annualRate;

    public int $tenureYears;

    /**
     * Accepts a plain string (or a LoanCategory, e.g. from `:category="$loanProduct->category"`
     * in a Blade component tag) rather than requiring a typed LoanCategory — Livewire's test
     * harness assigns initial params to typed public properties directly, before mount() runs,
     * so a strict LoanCategory type here would reject the raw enum/string it's given.
     */
    public function mount(string|LoanCategory $category = ''): void
    {
        $category = $category instanceof LoanCategory ? $category : LoanCategory::tryFrom($category);
        $category = ($category && LoanCalculatorPreset::for($category)) ? $category : LoanCategory::PersonalLoan;

        $this->category = $category->value;
        $this->applyPresetDefaults();
    }

    /**
     * @return array{label: string, min_amount: int, max_amount: int, default_amount: int, min_rate: float, max_rate: float, default_rate: float, min_years: int, max_years: int, default_years: int}
     */
    #[Computed]
    public function preset(): array
    {
        return LoanCalculatorPreset::for(LoanCategory::from($this->category));
    }

    /**
     * @return array{emi: float, total_payment: float, total_interest: float}
     */
    #[Computed]
    public function result(): array
    {
        return EmiCalculator::calculate($this->principal, $this->annualRate, $this->tenureYears * 12);
    }

    /**
     * @return array<int, array{year: int, principal_paid: float, interest_paid: float, balance: float}>
     */
    #[Computed]
    public function schedule(): array
    {
        return EmiCalculator::yearlySchedule($this->principal, $this->annualRate, $this->tenureYears * 12);
    }

    #[Computed]
    public function principalPercent(): float
    {
        $total = $this->result['total_payment'];

        return $total > 0 ? round($this->principal / $total * 100, 1) : 100.0;
    }

    #[Computed]
    public function interestPercent(): float
    {
        return round(100 - $this->principalPercent, 1);
    }

    /**
     * @return array<int, LoanCategory>
     */
    #[Computed]
    public function categories(): array
    {
        return LoanCalculatorPreset::supportedCategories();
    }

    public function selectCategory(string $categoryValue): void
    {
        $this->category = LoanCategory::from($categoryValue)->value;
        $this->applyPresetDefaults();
    }

    private function applyPresetDefaults(): void
    {
        $preset = LoanCalculatorPreset::for(LoanCategory::from($this->category));

        $this->principal = (float) $preset['default_amount'];
        $this->annualRate = $preset['default_rate'];
        $this->tenureYears = $preset['default_years'];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $preset = $this->preset;

        return [
            'principal' => ['required', 'numeric', "min:{$preset['min_amount']}", "max:{$preset['max_amount']}"],
            'annualRate' => ['required', 'numeric', "min:{$preset['min_rate']}", "max:{$preset['max_rate']}"],
            'tenureYears' => ['required', 'integer', "min:{$preset['min_years']}", "max:{$preset['max_years']}"],
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['principal', 'annualRate', 'tenureYears'], true)) {
            $this->validateOnly($property);
        }
    }
};
?>

<div>
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Loan type">
        @foreach ($this->categories as $option)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $category === $option->value ? 'true' : 'false' }}"
                wire:click="selectCategory('{{ $option->value }}')"
                @class([
                    'rounded-full px-4 py-2 text-sm font-medium transition-colors',
                    'bg-accent text-white' => $category === $option->value,
                    'bg-surface-2 text-ink-muted hover:text-ink' => $category !== $option->value,
                ])
            >
                {{ $option->getLabel() }}
            </button>
        @endforeach
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-2">
        <div class="flex flex-col gap-6">
            <div>
                <div class="flex items-baseline justify-between">
                    <label for="principal" class="text-sm font-medium text-ink">Loan amount</label>
                    <span class="font-mono text-sm text-ink-muted">₹{{ number_format($principal) }}</span>
                </div>
                <input
                    id="principal"
                    type="range"
                    min="{{ $this->preset['min_amount'] }}"
                    max="{{ $this->preset['max_amount'] }}"
                    step="{{ max((int) (($this->preset['max_amount'] - $this->preset['min_amount']) / 200), 1000) }}"
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
                    min="{{ $this->preset['min_rate'] }}"
                    max="{{ $this->preset['max_rate'] }}"
                    step="0.1"
                    wire:model.live="annualRate"
                    class="mt-2 w-full accent-accent"
                >
                @error('annualRate') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex items-baseline justify-between">
                    <label for="tenureYears" class="text-sm font-medium text-ink">Tenure</label>
                    <span class="font-mono text-sm text-ink-muted">{{ $tenureYears }} {{ Str::plural('year', $tenureYears) }}</span>
                </div>
                <input
                    id="tenureYears"
                    type="range"
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

    @if ($this->schedule !== [])
        <div class="mt-10 grid gap-8 lg:grid-cols-[auto_1fr]">
            <div class="flex flex-col items-center gap-4 lg:items-start">
                <p class="font-display text-lg font-semibold text-ink">Principal vs. interest</p>
                <div
                    class="h-40 w-40 shrink-0 rounded-full"
                    style="background: conic-gradient(var(--color-accent) 0% {{ $this->principalPercent }}%, var(--color-warn) {{ $this->principalPercent }}% 100%)"
                    role="img"
                    aria-label="{{ $this->principalPercent }}% principal, {{ $this->interestPercent }}% interest"
                ></div>
                <ul class="flex flex-col gap-2 text-sm">
                    <li class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-accent"></span>
                        <span class="text-ink-muted">Principal</span>
                        <span class="font-mono font-medium text-ink">{{ $this->principalPercent }}%</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-warn"></span>
                        <span class="text-ink-muted">Interest</span>
                        <span class="font-mono font-medium text-ink">{{ $this->interestPercent }}%</span>
                    </li>
                </ul>
            </div>

            <div>
                <p class="font-display text-lg font-semibold text-ink">Principal &amp; interest paid per year</p>
                <div class="mt-4 flex items-end gap-1.5 overflow-x-auto border-b border-line pb-1" style="min-height: 12rem">
                    @php
                        $maxYearTotal = max(1, ...array_map(fn ($row) => $row['principal_paid'] + $row['interest_paid'], $this->schedule));
                    @endphp
                    @foreach ($this->schedule as $row)
                        @php
                            $yearTotal = $row['principal_paid'] + $row['interest_paid'];
                            $barHeight = max(($yearTotal / $maxYearTotal) * 100, 2);
                            $principalShare = $yearTotal > 0 ? $row['principal_paid'] / $yearTotal * 100 : 0;
                            $interestShare = 100 - $principalShare;
                        @endphp
                        <div class="flex w-8 shrink-0 flex-col items-center gap-1.5" title="Year {{ $row['year'] }}: ₹{{ number_format($row['principal_paid']) }} principal, ₹{{ number_format($row['interest_paid']) }} interest">
                            <div class="flex w-full flex-col justify-end overflow-hidden rounded-t" style="height: 10rem">
                                <div style="height: {{ $barHeight }}%" class="flex w-full flex-col justify-end overflow-hidden">
                                    <div class="w-full bg-warn" style="height: {{ $interestShare }}%"></div>
                                    <div class="w-full bg-accent" style="height: {{ $principalShare }}%"></div>
                                </div>
                            </div>
                            <span class="font-mono text-[0.6rem] text-ink-faint">{{ $row['year'] }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-ink-faint">Year on the x-axis, amount repaid on the y-axis — early years lean toward interest, later years toward principal.</p>
            </div>
        </div>

        <div class="mt-10">
            <p class="font-display text-lg font-semibold text-ink">Full yearly breakdown</p>
            <div class="mt-4 overflow-x-auto rounded-xl border border-line">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-line bg-surface-2 text-left text-ink-muted">
                            <th class="px-4 py-2.5 font-medium">Year</th>
                            <th class="px-4 py-2.5 text-right font-medium">Principal paid</th>
                            <th class="px-4 py-2.5 text-right font-medium">Interest paid</th>
                            <th class="px-4 py-2.5 text-right font-medium">Total paid</th>
                            <th class="px-4 py-2.5 text-right font-medium">Balance remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->schedule as $row)
                            <tr class="border-b border-line last:border-0 odd:bg-surface even:bg-surface-2/50">
                                <td class="px-4 py-2.5 font-medium text-ink">{{ $row['year'] }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink">₹{{ number_format($row['principal_paid']) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink">₹{{ number_format($row['interest_paid']) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-muted">₹{{ number_format($row['principal_paid'] + $row['interest_paid']) }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-muted">₹{{ number_format($row['balance']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
