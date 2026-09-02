<?php

use App\Enums\LoanCategory;
use App\Models\Article;
use App\Models\LoanProduct;
use App\Support\Calculators\EmiCalculator;
use App\Support\Calculators\LoanCalculatorPreset;
use App\Support\Formatting\IndianNumberFormatter;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $category;

    public float $principal;

    public float $annualRate;

    public int $tenureYears;

    /**
     * The 3 tenures the visitor is comparing in the "Find your ideal Tenure"
     * table — independent of $tenureYears above, so picking a tenure to
     * compare there never touches the main calculator's own result.
     *
     * @var array<int, int>
     */
    public array $compareTenureYears = [];

    /**
     * The 3 rates the visitor is comparing in "Compare Rates & Savings" —
     * independent of $annualRate above, for the same reason.
     *
     * @var array<int, float>
     */
    public array $compareRates = [];

    /**
     * Renders the "About this loan" panel (explanation, tenure/rate
     * comparison, CTAs, related resources, FAQs) below the calculator. Only
     * the standalone /calculators/emi/{category} page turns this on — loan
     * product pages already show an equivalent section of their own and
     * would otherwise duplicate it.
     */
    public bool $showLoanDetails = true;

    /**
     * Accepts a plain string (or a LoanCategory, e.g. from `:category="$loanProduct->category"`
     * in a Blade component tag) rather than requiring a typed LoanCategory — Livewire's test
     * harness assigns initial params to typed public properties directly, before mount() runs,
     * so a strict LoanCategory type here would reject the raw enum/string it's given.
     */
    public function mount(string|LoanCategory $category = '', bool $showLoanDetails = true): void
    {
        $category = $category instanceof LoanCategory ? $category : LoanCategory::tryFrom($category);
        $category = ($category && LoanCalculatorPreset::for($category)) ? $category : LoanCategory::PersonalLoan;

        $this->category = $category->value;
        $this->showLoanDetails = $showLoanDetails;
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
     * @return array<int, array{year: int, principal_paid: float, interest_paid: float, total_paid: float, balance: float}>
     */
    #[Computed]
    public function schedule(): array
    {
        return EmiCalculator::yearlySchedule($this->principal, $this->annualRate, $this->tenureYears * 12);
    }

    /**
     * The monthly schedule, grouped by year so each year's <details> block can
     * list only the months that belong to it.
     *
     * @return array<int, array<int, array{month: int, year: int, month_in_year: int, principal_paid: float, interest_paid: float, total_paid: float, balance: float}>>
     */
    #[Computed]
    public function monthsByYear(): array
    {
        $months = EmiCalculator::monthlySchedule($this->principal, $this->annualRate, $this->tenureYears * 12);

        $byYear = [];
        foreach ($months as $row) {
            $byYear[$row['year']][] = $row;
        }

        return $byYear;
    }

    /**
     * The calendar month a given absolute schedule month (1 = the current
     * month) falls on — so the schedule reads as real dates starting from
     * whenever someone is actually looking at it, rather than an abstract
     * "Year 1 / Month 1" counted from a hypothetical disbursal date.
     */
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

    /**
     * The published LoanProduct backing the active category — source for the
     * calculator explanation copy, CTAs, and FAQs shown in the loan details
     * panel. Always resolves for a category that made it into $this->categories,
     * but guarded nullable since that's the contract LoanCalculatorPreset sets.
     */
    #[Computed]
    public function product(): ?LoanProduct
    {
        return LoanCalculatorPreset::productFor(LoanCategory::from($this->category));
    }

    /**
     * Every whole-year tenure the active category's preset allows — the
     * options offered in each of the 3 "Find your ideal Tenure" dropdowns.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function tenureOptions(): array
    {
        return $this->tenureOptionsFor($this->preset);
    }

    /**
     * The active category's allowed rates in quarter-point steps — the
     * options offered in each of the 3 "Compare Rates & Savings" dropdowns.
     *
     * @return array<int, float>
     */
    #[Computed]
    public function rateOptions(): array
    {
        return $this->rateOptionsFor($this->preset);
    }

    /**
     * EMI for each of the visitor's 3 chosen tenures, at the current amount
     * and rate — so "4 years vs 6 years vs 7 years" reflects tenures the
     * visitor actually picked, not ones the system guessed at.
     *
     * @return array<int, array{years: int, emi: float, total_interest: float, total_payment: float}>
     */
    #[Computed]
    public function tenureComparison(): array
    {
        return collect($this->compareTenureYears)
            ->map(fn (int $years) => [
                'years' => $years,
                ...EmiCalculator::calculate($this->principal, $this->annualRate, $years * 12),
            ])
            ->all();
    }

    /**
     * EMI for each of the visitor's 3 chosen rates, at the current amount
     * and tenure.
     *
     * @return array<int, array{rate: float, emi: float, total_interest: float, total_payment: float}>
     */
    #[Computed]
    public function rateComparison(): array
    {
        return collect($this->compareRates)
            ->map(fn (float $rate) => [
                'rate' => $rate,
                ...EmiCalculator::calculate($this->principal, $rate, $this->tenureYears * 12),
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function tenureOptionsFor(array $preset): array
    {
        return range($preset['min_years'], $preset['max_years']);
    }

    /**
     * @return array<int, float>
     */
    private function rateOptionsFor(array $preset): array
    {
        $options = [];

        for ($rate = $preset['min_rate']; $rate < $preset['max_rate']; $rate += 0.25) {
            $options[] = round($rate, 2);
        }
        $options[] = round($preset['max_rate'], 2);

        return array_values(array_unique($options));
    }

    /**
     * The 3 starting selections shown in a comparison's dropdowns before the
     * visitor changes any of them — spread across the low, middle and high
     * end of whatever options are available.
     *
     * @param  array<int, int|float>  $options
     * @return array<int, int|float>
     */
    private function defaultCompareSelections(array $options): array
    {
        $last = count($options) - 1;
        $middle = intdiv($last, 2);

        return [$options[0], $options[$middle], $options[$last]];
    }

    /**
     * Guides tagged to this loan category, falling back to general
     * (untagged) resources so the panel always has something to show.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Article>
     */
    #[Computed]
    public function relatedArticles(): \Illuminate\Database\Eloquent\Collection
    {
        return Article::query()
            ->published()
            ->forCategoryOrGeneral(LoanCategory::from($this->category))
            ->latest('published_at')
            ->limit(3)
            ->get();
    }

    /**
     * The lightweight eligibility calculator only covers Personal and Home
     * Loan today — every other category falls back to the general
     * eligibility picker, which still lets a visitor check eligibility.
     */
    #[Computed]
    public function eligibilityUrl(): ?string
    {
        $category = LoanCategory::from($this->category);

        if (Route::has('calculators.eligibility') && in_array($category, [LoanCategory::PersonalLoan, LoanCategory::HomeLoan], true)) {
            return route('calculators.eligibility', $category->value);
        }

        return Route::has('eligibility.index') ? route('eligibility.index') : null;
    }

    #[Computed]
    public function applyUrl(): ?string
    {
        return ($this->product && Route::has('loans.apply')) ? route('loans.apply', $this->product) : null;
    }

    /**
     * Amount and tenure reset to the new category's own defaults on every
     * switch — a personal loan's ₹5L default means nothing as a home loan
     * amount. The interest rate is different: each category's minimum ROI is
     * itself a meaningful, comparable figure (it's what gets advertised), so
     * a rate the visitor already dialed in is kept as long as it still falls
     * inside the new category's [min_rate, max_rate] band, and only snapped
     * to the new minimum when it doesn't.
     */
    public function selectCategory(string $categoryValue): void
    {
        $preset = LoanCalculatorPreset::for(LoanCategory::from($categoryValue));

        $this->category = $categoryValue;
        $this->principal = (float) $preset['default_amount'];
        $this->tenureYears = $preset['default_years'];
        $this->compareTenureYears = $this->defaultCompareSelections($this->tenureOptionsFor($preset));
        $this->compareRates = $this->defaultCompareSelections($this->rateOptionsFor($preset));

        if ($this->annualRate < $preset['min_rate'] || $this->annualRate > $preset['max_rate']) {
            $this->annualRate = $preset['min_rate'];
        }
    }

    private function applyPresetDefaults(): void
    {
        $preset = LoanCalculatorPreset::for(LoanCategory::from($this->category));

        $this->principal = (float) $preset['default_amount'];
        $this->annualRate = $preset['default_rate'];
        $this->tenureYears = $preset['default_years'];
        $this->compareTenureYears = $this->defaultCompareSelections($this->tenureOptionsFor($preset));
        $this->compareRates = $this->defaultCompareSelections($this->rateOptionsFor($preset));
    }

    /**
     * Indian digit-grouping (₹8,00,000, not ₹800,000) for every currency
     * figure the calculator displays.
     */
    public function formatAmount(int|float $amount): string
    {
        return IndianNumberFormatter::format($amount);
    }

    /**
     * Applies to both the slider and its paired number input — the slider's
     * own min/max attributes stop a drag from going out of range, but a
     * typed number has no such native enforcement, so this is the actual
     * backstop. It clamps the value to the product's real limit rather than
     * just flagging an error and leaving an out-of-range figure sitting in
     * $this->principal — the EMI shown must never be computed from a value
     * above what the product actually allows, whether that value arrived by
     * slider, by typing, or by a direct request to this Livewire component
     * (there's no separate "frontend-only" path to bypass; every property
     * update round-trips through this same server-side method).
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['principal', 'annualRate', 'tenureYears'], true)) {
            return;
        }

        $preset = $this->preset;
        [$min, $max, $label] = match ($property) {
            'principal' => [$preset['min_amount'], $preset['max_amount'], 'loan amount'],
            'annualRate' => [$preset['min_rate'], $preset['max_rate'], 'interest rate'],
            'tenureYears' => [$preset['min_years'], $preset['max_years'], 'tenure'],
        };

        $value = $this->{$property};

        if ($value < $min || $value > $max) {
            $clamped = max($min, min($max, $value));
            $this->{$property} = $property === 'tenureYears' ? (int) $clamped : (float) $clamped;
            $this->addError($property, "Adjusted the {$label} to stay within {$this->preset['label']}'s allowed range.");

            return;
        }

        $this->resetErrorBag($property);
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
                <div class="flex items-baseline justify-between gap-3">
                    <label for="principal" class="text-sm font-medium text-ink">Loan amount</label>
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
                    aria-label="Loan amount"
                    min="{{ $this->preset['min_amount'] }}"
                    max="{{ $this->preset['max_amount'] }}"
                    step="{{ max((int) (($this->preset['max_amount'] - $this->preset['min_amount']) / 200), 1000) }}"
                    wire:model.live="principal"
                    class="mt-2 w-full accent-accent"
                >
                <p class="mt-1 text-xs text-ink-faint" x-text="numberToIndianWords(String($wire.principal)) + ' Rupees only'"></p>
                <p class="mt-1 text-xs text-ink-faint">Maximum loan amount: ₹{{ $this->formatAmount($this->preset['max_amount']) }}</p>
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
                <p class="mt-1 text-xs text-ink-faint">
                    Indicative range for {{ $this->preset['label'] }}: {{ $this->preset['rate_note'] ?? number_format($this->preset['min_rate'], 2).'% – '.number_format($this->preset['max_rate'], 2).'%' }}
                </p>
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
                <p class="mt-1 text-xs text-ink-faint">Maximum tenure: {{ $this->preset['max_years'] }} {{ Str::plural('year', $this->preset['max_years']) }} ({{ $this->preset['max_years'] * 12 }} months)</p>
                @error('tenureYears') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex flex-col justify-center gap-5 rounded-2xl border border-line bg-surface-2 p-7">
            <div>
                <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Monthly EMI</p>
                <p class="mt-1 font-display text-4xl font-semibold text-accent">₹{{ $this->formatAmount($this->result['emi']) }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4 border-t border-line pt-5">
                <div>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total interest</p>
                    <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['total_interest']) }}</p>
                </div>
                <div>
                    <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">Total payment</p>
                    <p class="mt-1 text-lg font-medium text-ink">₹{{ $this->formatAmount($this->result['total_payment']) }}</p>
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
                        <div class="flex w-8 shrink-0 flex-col items-center gap-1.5" title="{{ $this->yearLabel($this->monthsByYear[$row['year']]) }}: ₹{{ $this->formatAmount($row['principal_paid']) }} principal, ₹{{ $this->formatAmount($row['interest_paid']) }} interest">
                            <div class="flex w-full flex-col justify-end overflow-hidden rounded-t" style="height: 10rem">
                                <div style="height: {{ $barHeight }}%" class="flex w-full flex-col justify-end overflow-hidden">
                                    <div class="w-full bg-warn" style="height: {{ $interestShare }}%"></div>
                                    <div class="w-full bg-accent" style="height: {{ $principalShare }}%"></div>
                                </div>
                            </div>
                            <span class="font-mono text-[0.6rem] text-ink-faint">{{ $this->monthDate($this->monthsByYear[$row['year']][0]['month'])->format('Y') }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-ink-faint">Time on the x-axis, amount repaid on the y-axis — early on leans toward interest, later on toward principal.</p>
            </div>
        </div>

        <div class="mt-10">
            <p class="font-display text-lg font-semibold text-ink">Full breakdown, starting this month</p>
            <p class="mt-1 text-xs text-ink-faint">Assumes your first EMI falls this month — click a period to see every month within it.</p>
            <div class="mt-4 rounded-xl border border-line">
                <div class="hidden border-b border-line bg-surface-2 px-4 py-2.5 text-left text-xs font-medium text-ink-muted sm:grid sm:grid-cols-[2rem_1fr_1fr_1fr_1fr]">
                    <span></span>
                    <span>Period</span>
                    <span class="text-right">Principal paid</span>
                    <span class="text-right">Interest paid</span>
                    <span class="text-right">Total paid</span>
                </div>
                @foreach ($this->schedule as $row)
                    <details class="group border-b border-line last:border-0 odd:bg-surface even:bg-surface-2/50">
                        <summary class="grid cursor-pointer list-none grid-cols-2 items-center gap-1 px-4 py-3 text-sm hover:bg-surface-2 sm:grid-cols-[2rem_1fr_1fr_1fr_1fr]">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 text-ink-faint transition-transform group-open:rotate-90"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4l6 6-6 6" /></svg>
                            <span class="font-medium text-ink">{{ $this->yearLabel($this->monthsByYear[$row['year']]) }}</span>
                            <span class="text-right font-mono text-ink sm:text-right">₹{{ $this->formatAmount($row['principal_paid']) }}</span>
                            <span class="text-right font-mono text-ink sm:text-right">₹{{ $this->formatAmount($row['interest_paid']) }}</span>
                            <span class="col-span-2 text-right font-mono text-ink-muted sm:col-span-1">₹{{ $this->formatAmount($row['total_paid']) }}</span>
                        </summary>

                        <div class="overflow-x-auto border-t border-line bg-surface px-4 py-3">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-ink-faint">
                                        <th class="py-1.5 pr-3 font-medium">Month</th>
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

    @if ($this->showLoanDetails && $this->product)
        @php($product = $this->product)
        <div class="mt-14 border-t border-line pt-10">
            <h2 class="font-display text-2xl font-semibold text-ink">About the {{ $this->preset['label'] }}</h2>

            @if ($product->calculator_explanation || $product->summary)
                <div class="prose prose-neutral mt-4 max-w-2xl text-ink-muted [&_h3]:font-display [&_h3]:text-ink [&_p]:leading-relaxed">
                    {!! $product->calculator_explanation ?: '<p>'.e($product->summary).'</p>' !!}
                </div>
            @endif

            <div class="mt-10 grid gap-8 lg:grid-cols-2">
                <div>
                    <h3 class="font-display text-lg font-semibold text-ink">Find your ideal Tenure</h3>
                    <p class="mt-1 text-xs text-ink-faint">
                        Pick 3 tenures to compare — same ₹{{ $this->formatAmount($this->principal) }} at {{ number_format($this->annualRate, 2) }}% each time.
                    </p>
                    <div class="mt-4 overflow-x-auto rounded-xl border border-line">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line bg-surface-2 text-left text-xs text-ink-muted">
                                    <th class="px-3 py-2 font-medium">Tenure</th>
                                    <th class="px-3 py-2 text-right font-medium">EMI</th>
                                    <th class="px-3 py-2 text-right font-medium">Total interest</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->tenureComparison as $index => $row)
                                    <tr class="border-t border-line/60">
                                        <td class="px-3 py-2 text-ink">
                                            <select
                                                wire:model.live="compareTenureYears.{{ $index }}"
                                                aria-label="Tenure option {{ $index + 1 }}"
                                                class="rounded-md border border-line-strong bg-surface px-2 py-1 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                                            >
                                                @foreach ($this->tenureOptions as $option)
                                                    <option value="{{ $option }}">{{ $option }} {{ Str::plural('year', $option) }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono text-ink">₹{{ $this->formatAmount($row['emi']) }}</td>
                                        <td class="px-3 py-2 text-right font-mono text-ink-muted">₹{{ $this->formatAmount($row['total_interest']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h3 class="font-display text-lg font-semibold text-ink">Compare Rates &amp; Savings</h3>
                    <p class="mt-1 text-xs text-ink-faint">
                        Pick 3 rates to compare — same ₹{{ $this->formatAmount($this->principal) }} over {{ $this->tenureYears }} {{ Str::plural('year', $this->tenureYears) }} each time.
                    </p>
                    <div class="mt-4 overflow-x-auto rounded-xl border border-line">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line bg-surface-2 text-left text-xs text-ink-muted">
                                    <th class="px-3 py-2 font-medium">Rate</th>
                                    <th class="px-3 py-2 text-right font-medium">EMI</th>
                                    <th class="px-3 py-2 text-right font-medium">Total interest</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->rateComparison as $index => $row)
                                    <tr class="border-t border-line/60">
                                        <td class="px-3 py-2 text-ink">
                                            <select
                                                wire:model.live="compareRates.{{ $index }}"
                                                aria-label="Rate option {{ $index + 1 }}"
                                                class="rounded-md border border-line-strong bg-surface px-2 py-1 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40"
                                            >
                                                @foreach ($this->rateOptions as $option)
                                                    <option value="{{ $option }}">{{ number_format($option, 2) }}%</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono text-ink">₹{{ $this->formatAmount($row['emi']) }}</td>
                                        <td class="px-3 py-2 text-right font-mono text-ink-muted">₹{{ $this->formatAmount($row['total_interest']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-10 flex flex-wrap gap-3">
                @if ($this->eligibilityUrl)
                    <x-ui.button tag="a" :href="$this->eligibilityUrl" size="lg">Check Your Eligibility</x-ui.button>
                @endif
                @if ($this->applyUrl)
                    <x-ui.button tag="a" :href="$this->applyUrl" variant="secondary" size="lg">Apply for this loan</x-ui.button>
                @endif
            </div>

            @if ($this->relatedArticles->isNotEmpty())
                <div class="mt-12">
                    <h3 class="font-display text-lg font-semibold text-ink">Guides related to {{ $this->preset['label'] }}</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        @foreach ($this->relatedArticles as $article)
                            <a href="{{ route('resources.show', $article) }}" class="group">
                                <x-ui.card class="h-full transition-colors transition-shadow group-hover:bg-accent-soft group-hover:shadow-md group-hover:animate-card-swing">
                                    <p class="font-medium text-ink group-hover:text-accent">{{ $article->title }}</p>
                                    @if ($article->excerpt)
                                        <p class="mt-1.5 text-xs text-ink-muted">{{ $article->excerpt }}</p>
                                    @endif
                                </x-ui.card>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($product->faqs->isNotEmpty())
                <x-site.faq-accordion :faqs="$product->faqs" :heading="$this->preset['label'].' EMI — frequently asked questions'" />
                <x-site.faq-json-ld :faqs="$product->faqs" />
            @endif
        </div>
    @endif
</div>
