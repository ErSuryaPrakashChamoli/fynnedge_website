<?php

use App\Modules\DebtConsolidation\DataTransferObjects\FlagRaised;
use App\Modules\DebtConsolidation\DataTransferObjects\IndividualFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioResult;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RiskFlag;
use App\Modules\DebtConsolidation\Services\CaseInputBuilder;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Modules\DebtConsolidation\Services\DebtPresenter;
use App\Modules\DebtConsolidation\Services\Finance\DebtConsolidationEngine;
use App\Support\Formatting\IndianNumberFormatter;
use App\Support\Pages\DebtConsolidationPages;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * The debt consolidation savings calculator under /calculators: credit cards
 * (balance, rate, what is paid each month) and loans (balance, rate, months
 * left, foreclosure charge) against one consolidation loan. Every figure comes
 * from the Debt Consolidation engine, run once with every tenure from 1 to 7
 * years so the best tenure can be shown. The "saving" is always the total-cost
 * difference after fees and charges — a lower EMI is never presented as a
 * saving (docs/debt-consolidation/06-financial-engine.md).
 */
new class extends Component
{
    public float $cardOutstanding = 200000;

    public float $cardRate = 42;

    public float $cardPayment = 10000;

    public float $loanOutstanding = 500000;

    public float $loanRate = 16;

    public int $loanMonths = 36;

    public float $foreclosurePercent = 0;

    public float $newRate = 14;

    public int $newYears = 3;

    public float $feePercent = 2;

    public float $monthlyIncome = 0;

    /** Until the visitor sets the card payment, it follows the minimum due on the balance. */
    #[Locked]
    public bool $cardPaymentEdited = false;

    /**
     * Allowed range of each input: [min, max].
     *
     * @var array<string, array{0: int|float, 1: int|float}>
     */
    private const LIMITS = [
        'cardOutstanding' => [0, 5_000_000],
        'cardRate' => [12, 48],
        'cardPayment' => [0, 5_000_000],
        'loanOutstanding' => [0, 5_000_000],
        'loanRate' => [8, 36],
        'loanMonths' => [3, 120],
        'foreclosurePercent' => [0, 6],
        'newRate' => [8, 30],
        'newYears' => [1, 7],
        'feePercent' => [0, 5],
        'monthlyIncome' => [0, 10_000_000],
    ];

    /**
     * Query-string names for a shareable link, by property.
     *
     * @var array<string, string>
     */
    private const QUERY = [
        'cardOutstanding' => 'cards',
        'cardRate' => 'card_rate',
        'cardPayment' => 'card_payment',
        'loanOutstanding' => 'loans',
        'loanRate' => 'loan_rate',
        'loanMonths' => 'months',
        'foreclosurePercent' => 'foreclosure',
        'newRate' => 'rate',
        'newYears' => 'years',
        'feePercent' => 'fee',
        'monthlyIncome' => 'income',
    ];

    private const INTEGERS = ['loanMonths', 'newYears'];

    private const SHOWN_FLAGS = [RiskFlag::LowerEmiHigherCost, RiskFlag::TenureExtended, RiskFlag::ProposedRateNotLower, RiskFlag::PaymentBelowInterest];

    /**
     * A shared link (?cards=…&rate=…) reopens the same figures. Each value is
     * checked and kept inside its range, like a typed one.
     */
    public function mount(): void
    {
        foreach (self::QUERY as $property => $name) {
            $value = request()->query($name);

            if (! is_numeric($value)) {
                continue;
            }

            [$min, $max] = self::LIMITS[$property];
            $this->{$property} = $this->cast($property, max($min, min($max, (float) $value)));

            if ($property === 'cardPayment') {
                $this->cardPaymentEdited = true;
            }
        }

        if (! $this->cardPaymentEdited) {
            $this->cardPayment = $this->minimumDue();
        }
    }

    public function updated(string $property): void
    {
        if (! isset(self::LIMITS[$property])) {
            return;
        }

        [$min, $max] = self::LIMITS[$property];

        // An emptied field leaves the typed property unset — treat it as below the minimum.
        $value = $this->{$property} ?? null;

        if ($value === null || $value < $min || $value > $max) {
            $this->{$property} = $this->cast($property, max($min, min($max, $value ?? $min)));
            $this->addError($property, __('Adjusted to stay within the allowed range.'));
        } else {
            $this->resetErrorBag($property);
        }

        if ($property === 'cardPayment') {
            $this->cardPaymentEdited = true;
        }

        if ($property === 'cardOutstanding' && ! $this->cardPaymentEdited) {
            $this->cardPayment = $this->minimumDue();
        }
    }

    /**
     * "Use" on a row of the tenure finder.
     */
    public function useTenure(int $years): void
    {
        [$min, $max] = self::LIMITS['newYears'];
        $this->newYears = max((int) $min, min((int) $max, $years));
        $this->resetErrorBag('newYears');
    }

    /**
     * Everything shown, from ONE engine run: the debts as they are, and a
     * consolidation loan at each tenure from 1 to 7 years.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function analysis(): ?array
    {
        $cards = (float) ($this->cardOutstanding ?? 0);
        $loans = (float) ($this->loanOutstanding ?? 0);
        $names = ['cards' => __('Credit cards'), 'loans' => __('Your loans')];
        $liabilities = [];

        if ($cards > 0) {
            $payment = (float) ($this->cardPayment ?? 0);
            $liabilities[] = new LiabilityInput('cards', LiabilityType::CreditCardOutstanding, $names['cards'], $cards, (float) ($this->cardRate ?? 42), monthlyPayment: $payment > 0 ? $payment : null);
        }

        if ($loans > 0) {
            $liabilities[] = new LiabilityInput('loans', LiabilityType::PersonalLoan, $names['loans'], $loans, (float) ($this->loanRate ?? 16), remainingMonths: (int) ($this->loanMonths ?? 36), foreclosureChargePercent: (float) ($this->foreclosurePercent ?? 0));
        }

        if ($liabilities === []) {
            return null;
        }

        [$minYears, $maxYears] = self::LIMITS['newYears'];
        $scenarios = array_map(
            fn (int $years): ScenarioInput => new ScenarioInput('years-'.$years, __('Consolidation loan'), (float) ($this->newRate ?? 14), $years * 12, processingFeePercent: (float) ($this->feePercent ?? 2), route: FinancingRoute::PersonalLoan),
            range((int) $minYears, (int) $maxYears),
        );
        $income = (float) ($this->monthlyIncome ?? 0);
        $assessment = app(DebtConsolidationEngine::class)->assess(app(CaseInputBuilder::class)->forCalculator(
            ApplicantType::Individual,
            $liabilities,
            $scenarios,
            new IndividualFinances($income > 0 ? $income : null),
        ));

        $tenures = [];

        foreach ($assessment->scenarios as $index => $row) {
            $result = $row['result'];
            $tenures[] = [
                'years' => (int) $minYears + $index,
                'emi' => $result->proposedMonthlyOutflow,
                'interest' => $result->proposedInterest,
                'total' => $result->proposedTotalOutflow,
                'net' => $result->netFinancialBenefit,
                'months' => $result->proposedMonths,
            ];
        }

        $selectedIndex = max(0, min(count($tenures) - 1, (int) ($this->newYears ?? 3) - (int) $minYears));
        $row = $assessment->scenarios[$selectedIndex];
        $result = $row['result'];
        $currentEmi = $result->existingMonthlyOutflow;
        $saving = array_values(array_filter($tenures, fn (array $tenure): bool => $tenure['net'] > 0));
        $best = $saving === [] ? null : collect($saving)->sortByDesc('net')->first();
        $withinToday = collect($saving)->filter(fn (array $tenure): bool => $tenure['emi'] <= $currentEmi)->sortByDesc('net')->first();
        $lowestSavingEmi = $saving === [] ? null : collect($saving)->sortByDesc('years')->first();

        $debts = [];

        foreach ($assessment->liabilities as $key => $liability) {
            $summary = $liability['projection']->summary();
            $debts[] = [
                'key' => $key,
                'name' => $names[$key] ?? $key,
                'monthly' => $summary['monthly_obligation'],
                'months' => $summary['remaining_months'],
                'interest' => $summary['remaining_interest'],
                'assumed' => $summary['repayment_assumed'],
            ];
        }

        $flags = collect([...$result->flags, ...$assessment->flags])
            ->filter(fn (FlagRaised $flag): bool => in_array($flag->flag, self::SHOWN_FLAGS, true))
            ->map(fn (FlagRaised $flag): string => DebtPresenter::flagSentence($flag->toArray(), $names))
            ->unique()
            ->values()
            ->all();

        $affordability = $income > 0 ? [
            'today' => $row['affordability']->currentValue,
            'after' => $row['affordability']->proposedValue,
            'today_band' => $row['affordability']->currentBand->getLabel(),
            'after_band' => $row['affordability']->proposedBand->getLabel(),
        ] : null;

        return [
            'total_debt' => $cards + $loans,
            'current_emi' => $currentEmi,
            'new_emi' => $result->proposedMonthlyOutflow,
            'emi_change' => $result->monthlyCashFlowDifference,
            'emi_change_percent' => $currentEmi > 0 ? round($result->monthlyCashFlowDifference / $currentEmi * 100, 1) : 0.0,
            'current_total' => $result->existingTotalOutflow,
            'new_total' => $result->proposedTotalOutflow,
            'current_interest' => $result->existingInterest,
            'new_interest' => $result->proposedInterest,
            'interest_saved' => round($result->existingInterest - $result->proposedInterest, 2),
            'costs' => $result->totalConsolidationCosts,
            'net_benefit' => $result->netFinancialBenefit,
            'current_months' => $result->existingMonths,
            'new_months' => $result->proposedMonths,
            'loan_amount' => $result->loanAmount,
            'break_even_month' => $result->costBreakEvenMonth,
            'break_even_holds' => $result->costRecoveryHolds,
            'yearly' => $result->yearly,
            'tenures' => $tenures,
            'best_years' => $best['years'] ?? null,
            'within_today_years' => $withinToday['years'] ?? null,
            'lowest_saving_emi_years' => $lowestSavingEmi['years'] ?? null,
            'debts' => $debts,
            'warnings' => $flags,
            'affordability' => $affordability,
            'insights' => $this->insights($result, $tenures, $best, $withinToday, $lowestSavingEmi, $debts, $affordability),
        ];
    }

    /**
     * A shareable link that reopens these exact figures.
     */
    #[Computed]
    public function shareUrl(): string
    {
        $query = [];

        foreach (self::QUERY as $property => $name) {
            if ($property === 'cardPayment' && ! $this->cardPaymentEdited) {
                continue;
            }

            $query[$name] = $this->{$property} ?? null;
        }

        return route('calculators.debt-consolidation', array_filter($query, fn ($value): bool => $value !== null));
    }

    /**
     * The free detailed assessment while that page is live; otherwise a call
     * back from an advisor (the prefilled message is saved, so it stays English).
     *
     * @return array{url: string, label: string}
     */
    #[Computed]
    public function nextStep(): array
    {
        $assessment = DebtConsolidationPages::liveUrl('assessment');

        return $assessment !== null
            ? ['url' => $assessment, 'label' => __('Get a free detailed assessment')]
            : ['url' => route('contact', ['message' => 'I would like advice on consolidating my loans and credit cards.']), 'label' => __('Talk to an advisor')];
    }

    public function formatAmount(int|float $amount): string
    {
        return IndianNumberFormatter::format($amount);
    }

    /**
     * The month and year a repayment ends, counting from this month.
     */
    public function monthYear(int $months): string
    {
        return now()->startOfMonth()->addMonths($months)->translatedFormat('M Y');
    }

    public function minimumDuePercent(): float
    {
        return (float) DebtConsolidationSettings::get('revolving_minimum_payment_percent');
    }

    private function minimumDue(): float
    {
        return round((float) ($this->cardOutstanding ?? 0) * $this->minimumDuePercent() / 100);
    }

    private function cast(string $property, int|float $value): int|float
    {
        return in_array($property, self::INTEGERS, true) ? (int) round($value) : (float) $value;
    }

    /**
     * Plain-language reading of the figures, each sentence computed.
     *
     * @param  list<array{years: int, emi: float, interest: float, total: float, net: float, months: int}>  $tenures
     * @param  list<array{key: string, name: string, monthly: float, months: int, interest: float, assumed: bool}>  $debts
     * @param  array{today: ?float, after: ?float, today_band: string, after_band: string}|null  $affordability
     * @return list<string>
     */
    private function insights(ScenarioResult $result, array $tenures, ?array $best, ?array $withinToday, ?array $lowestSavingEmi, array $debts, ?array $affordability): array
    {
        $years = (int) ($this->newYears ?? 3);
        $insights = [];

        $insights[] = $result->netFinancialBenefit > 0
            ? __('Over :years, consolidating saves ₹:net in total after ₹:costs of fees and charges.', ['years' => trans_choice(':count year|:count years', $years), 'net' => $this->formatAmount($result->netFinancialBenefit), 'costs' => $this->formatAmount($result->totalConsolidationCosts)])
            : __('Over :years, you would repay ₹:extra more in total than by keeping your current debts.', ['years' => trans_choice(':count year|:count years', $years), 'extra' => $this->formatAmount(abs($result->netFinancialBenefit))]);

        foreach ($debts as $debt) {
            if ($debt['key'] === 'cards' && ! $debt['assumed']) {
                $insights[] = __('Paying ₹:payment a month, your cards take :months to clear and cost ₹:interest in interest.', ['payment' => $this->formatAmount($debt['monthly']), 'months' => trans_choice(':count month|:count months', $debt['months']), 'interest' => $this->formatAmount($debt['interest'])]);
            }
        }

        if ($best === null) {
            $insights[] = __('No tenure from 1 to 7 years saves money at :rate% with a :fee% fee — a lower rate or a lower fee would be needed.', ['rate' => $this->newRate, 'fee' => $this->feePercent]);
        } else {
            if ($withinToday !== null && $withinToday['years'] !== $years) {
                $insights[] = __('A :years loan keeps your EMI below today’s (₹:emi a month) and saves ₹:net.', ['years' => __(':count-year', ['count' => $withinToday['years']]), 'emi' => $this->formatAmount($withinToday['emi']), 'net' => $this->formatAmount($withinToday['net'])]);
            }

            if ($lowestSavingEmi !== null && $lowestSavingEmi['years'] !== $years && $lowestSavingEmi['years'] !== ($withinToday['years'] ?? null)) {
                $insights[] = __('The lowest EMI that still saves money is ₹:emi a month, over :years (saving ₹:net).', ['emi' => $this->formatAmount($lowestSavingEmi['emi']), 'years' => trans_choice(':count year|:count years', $lowestSavingEmi['years']), 'net' => $this->formatAmount($lowestSavingEmi['net'])]);
            }
        }

        if ($result->costBreakEvenMonth !== null && $result->totalConsolidationCosts > 0) {
            $insights[] = $result->costRecoveryHolds
                ? __('The interest you save pays back the fees and charges by month :month (:date).', ['month' => $result->costBreakEvenMonth, 'date' => $this->monthYear($result->costBreakEvenMonth)])
                : __('The fees are paid back by month :month, but over the longer repayment you end up paying more interest.', ['month' => $result->costBreakEvenMonth]);
        }

        if ($affordability !== null && $affordability['after'] !== null) {
            $insights[] = __('After consolidating, :after% of your income goes to EMIs (today :today%). Most lenders look for 40–50% or less.', ['after' => round($affordability['after'], 1), 'today' => round((float) $affordability['today'], 1)]);
        }

        return $insights;
    }
};
?>

@php
    $analysis = $this->analysis;
    $boxClass = 'rounded-md border border-line-strong bg-surface px-2 py-1 text-right text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40';
    $groups = [
        [__('Credit cards'), [
            ['cardOutstanding', __('Total outstanding on your cards'), 5000, 'amount', null],
            ['cardRate', __('Card interest (p.a.)'), 0.5, '%', null],
            ['cardPayment', __('What you pay on your cards each month'), 500, 'amount', __('Starts at today’s minimum due (:percent% of the balance). Change it to what you really pay.', ['percent' => rtrim(rtrim(number_format($this->minimumDuePercent(), 2), '0'), '.')])],
        ]],
        [__('Loans'), [
            ['loanOutstanding', __('Total outstanding on your loans'), 10000, 'amount', __('Personal, consumer and other loans you want to close')],
            ['loanRate', __('Loan interest (p.a.)'), 0.25, '%', null],
            ['loanMonths', __('Months left on your loans'), 1, __('months'), null],
            ['foreclosurePercent', __('Foreclosure charge on your loans'), 0.5, '%', __('From your loan agreement; GST is added.')],
        ]],
        [__('The consolidation loan'), [
            ['newRate', __('New loan interest (p.a.)'), 0.25, '%', null],
            ['newYears', __('New loan tenure'), 1, __('years'), null],
            ['feePercent', __('Processing fee'), 0.25, '%', __('Deducted from the loan, with GST.')],
        ]],
        [__('Your income (optional)'), [
            ['monthlyIncome', __('Net monthly income'), 1000, 'amount', __('Shows how much of your income goes to EMIs.')],
        ]],
    ];
    $peak = $analysis ? max($analysis['current_emi'], $analysis['new_emi'], 1) : 1;
    $saves = $analysis && $analysis['net_benefit'] > 0;
@endphp

<div class="flex flex-col gap-10">
    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:items-start">
        {{-- Inputs --}}
        <div class="flex flex-col gap-5">
            @foreach ($groups as [$groupLabel, $fields])
                <fieldset class="rounded-2xl border border-line bg-surface p-5 shadow-sm">
                    <legend class="px-1 font-display text-base font-semibold text-ink">{{ $groupLabel }}</legend>
                    <div class="mt-1 flex flex-col gap-5">
                        @foreach ($fields as [$field, $label, $step, $unit, $hint])
                            @php $max = $field === 'cardPayment' ? max(1000, (int) ceil(max((float) ($cardOutstanding ?? 0), 1000) / 2)) : self::LIMITS[$field][1]; @endphp
                            <div>
                                <div class="flex items-baseline justify-between gap-3">
                                    <div>
                                        <label for="{{ $field }}" class="text-sm font-medium text-ink">{{ $label }}</label>
                                        @if ($hint) <p class="text-xs text-ink-faint">{{ $hint }}</p> @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1 font-mono text-sm text-ink-muted">
                                        @if ($unit === 'amount')
                                            ₹
                                            <input id="{{ $field }}" type="text" inputmode="numeric" autocomplete="off" data-min="{{ self::LIMITS[$field][0] }}" data-max="{{ self::LIMITS[$field][1] }}" x-data="calculatorInput('{{ $field }}', { currency: true })" x-on:focus="onFocus()" x-on:input="onInput()" x-on:blur="commit()" x-on:keydown.enter.prevent="$el.blur()" class="w-28 {{ $boxClass }}">
                                        @else
                                            <input id="{{ $field }}" type="number" inputmode="decimal" min="{{ self::LIMITS[$field][0] }}" max="{{ self::LIMITS[$field][1] }}" step="{{ $step }}" data-min="{{ self::LIMITS[$field][0] }}" data-max="{{ self::LIMITS[$field][1] }}" x-data="calculatorInput('{{ $field }}')" x-on:input="onInput()" x-on:blur="commit()" x-on:keydown.enter.prevent="$el.blur()" class="w-16 {{ $boxClass }}">
                                            {{ $unit }}
                                        @endif
                                    </div>
                                </div>
                                <input type="range" aria-label="{{ $label }}" min="{{ self::LIMITS[$field][0] }}" max="{{ $max }}" step="{{ $step }}" wire:model.live="{{ $field }}" class="mt-2 w-full accent-accent">
                                @if ($unit === 'amount')
                                    <p class="mt-1 text-xs text-ink-faint" x-text="Number($wire.{{ $field }}) > 0 ? numberToIndianWords(String($wire.{{ $field }})) + @js(' '.__('Rupees only')) : ''"></p>
                                @endif
                                @error($field) <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>

        {{--
            Headline results. The inputs column is the taller one, so on desktop the
            results follow the visitor down it (sticky below the site header) instead
            of leaving an empty column beside the lower inputs.
        --}}
        <div class="flex flex-col gap-5 lg:sticky lg:top-below-header" aria-live="polite" wire:loading.class="opacity-70" data-results>
            @if ($analysis === null)
                <div class="rounded-2xl border border-line bg-surface-2 p-7 text-sm text-ink-muted">{{ __('Enter what you owe on credit cards or loans to compare.') }}</div>
            @else
                <div @class([
                    'relative overflow-hidden rounded-3xl border p-6 shadow-sm',
                    'border-pass/40 bg-gradient-to-br from-pass-soft to-surface' => $saves,
                    'border-warn/40 bg-gradient-to-br from-warn-soft to-surface' => ! $saves,
                ]) data-verdict>
                    <p @class(['font-mono text-[0.65rem] font-semibold uppercase tracking-wider', 'text-pass' => $saves, 'text-warn' => ! $saves])>{{ $saves ? __('Total saving') : __('Extra cost') }}</p>
                    <p @class(['mt-1 font-display text-4xl font-semibold sm:text-5xl', 'text-pass' => $saves, 'text-warn' => ! $saves])>₹{{ $this->formatAmount(abs($analysis['net_benefit'])) }}</p>
                    <p class="mt-1 text-sm text-ink-muted">
                        {{ $saves ? __('Less to repay in total over :years, after the processing fee and charges.', ['years' => trans_choice(':count year|:count years', $newYears)]) : __('More to repay in total than keeping your current debts, even if the EMI is lower.') }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full bg-surface/80 px-3 py-1 font-medium text-ink ring-1 ring-line">{{ __('EMI ₹:from → ₹:to', ['from' => $this->formatAmount($analysis['current_emi']), 'to' => $this->formatAmount($analysis['new_emi'])]) }}</span>
                        <span class="rounded-full bg-surface/80 px-3 py-1 font-medium text-ink ring-1 ring-line">{{ __('Debt-free :from → :to', ['from' => $this->monthYear($analysis['current_months']), 'to' => $this->monthYear($analysis['new_months'])]) }}</span>
                        @if ($analysis['break_even_month'] !== null && $analysis['costs'] > 0)
                            <span class="rounded-full bg-surface/80 px-3 py-1 font-medium text-ink ring-1 ring-line">{{ __('Fees recovered by month :month', ['month' => $analysis['break_even_month']]) }}</span>
                        @endif
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="rounded-2xl border border-line bg-surface p-5">
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Monthly EMI') }}</p>
                        <div class="mt-3 flex items-end justify-center gap-6 border-b border-line" style="height: 10rem" data-emi-chart>
                            @foreach ([[__('Today'), $analysis['current_emi'], 'bg-ink-faint/40'], [__('After consolidating'), $analysis['new_emi'], 'bg-accent']] as [$barLabel, $amount, $barClass])
                                <div class="flex h-full w-20 flex-col items-center justify-end gap-1.5">
                                    <span class="font-mono text-xs font-semibold text-ink">₹{{ $this->formatAmount($amount) }}</span>
                                    <div class="w-full rounded-t-lg transition-all duration-500 {{ $barClass }}" style="height: {{ max(2, round($amount / $peak * 78, 1)) }}%"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-1.5 flex justify-center gap-6 text-[0.7rem] text-ink-muted"><span class="w-20 text-center">{{ __('Today') }}</span><span class="w-20 text-center">{{ __('After consolidating') }}</span></div>
                        <p class="mt-3 text-center text-xs text-ink-muted">
                            @if ($analysis['emi_change'] >= 0)
                                {{ __('₹:amount a month lower (:percent% less than today)', ['amount' => $this->formatAmount($analysis['emi_change']), 'percent' => $analysis['emi_change_percent']]) }}
                            @else
                                {{ __('₹:amount a month higher than today', ['amount' => $this->formatAmount(abs($analysis['emi_change']))]) }}
                            @endif
                        </p>
                    </div>

                    {{-- interest today − interest on the new loan − fees and charges = the result above, exactly --}}
                    <div class="rounded-2xl border border-line bg-surface p-5" data-breakdown>
                        <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Where the result comes from') }}</p>
                        @php $scale = max($analysis['current_interest'], $analysis['new_interest'], 1); @endphp
                        <dl class="mt-3 flex flex-col gap-3 text-xs">
                            @foreach ([
                                [__('Interest on your current debts'), $analysis['current_interest'], 'bg-ink-faint/40', ''],
                                [__('Interest on the new loan'), $analysis['new_interest'], 'bg-accent', '−'],
                                [__('Fees, GST and foreclosure charges'), $analysis['costs'], 'bg-warn', '−'],
                            ] as [$lineLabel, $amount, $barClass, $sign])
                                <div>
                                    <div class="flex justify-between gap-2"><dt class="text-ink-muted">{{ $lineLabel }}</dt><dd class="shrink-0 whitespace-nowrap font-mono font-semibold text-ink">{{ $sign }}₹{{ $this->formatAmount($amount) }}</dd></div>
                                    <div class="mt-1 h-1.5 rounded-full bg-surface-2"><div class="h-1.5 rounded-full transition-all duration-500 {{ $barClass }}" style="width: {{ min(100, round($amount / $scale * 100, 1)) }}%"></div></div>
                                </div>
                            @endforeach
                            <div class="flex justify-between gap-2 border-t border-line pt-2"><dt class="font-semibold text-ink">{{ $saves ? __('Total saving') : __('Extra cost') }}</dt><dd @class(['font-mono font-semibold', 'text-pass' => $saves, 'text-warn' => ! $saves])>{{ $saves ? '' : '−' }}₹{{ $this->formatAmount(abs($analysis['net_benefit'])) }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="rounded-2xl border border-accent/20 bg-accent-soft/50 p-5" data-insights>
                    <p class="font-display text-base font-semibold text-ink">{{ __('What this means for you') }}</p>
                    <ul class="mt-3 flex flex-col gap-2.5 text-sm text-ink">
                        @foreach ($analysis['insights'] as $insight)
                            <li class="flex gap-2.5"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="mt-0.5 h-4 w-4 shrink-0 text-accent" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m8 5 5 5-5 5" /></svg><span>{{ $insight }}</span></li>
                        @endforeach
                    </ul>
                </div>

                @foreach ($analysis['warnings'] as $warning)
                    <p class="flex gap-2 rounded-lg bg-warn-soft p-3 text-xs text-ink"><span aria-hidden="true">⚠</span>{{ $warning }}</p>
                @endforeach
            @endif
        </div>
    </div>

    @if ($analysis !== null)
        <div class="grid gap-8 lg:grid-cols-2">
            {{-- Tenure finder --}}
            <div class="rounded-2xl border border-line bg-surface p-5 shadow-sm" data-tenure-finder>
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('Find your best tenure') }}</h2>
                <p class="mt-1 text-xs text-ink-muted">{{ __('The same debts and rate, over every tenure. A longer tenure lowers the EMI but adds interest.') }}</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-[0.7rem] uppercase tracking-wide text-ink-faint">
                            <tr><th class="py-2 pr-2">{{ __('Tenure') }}</th><th class="py-2 pr-2 text-right">{{ __('EMI') }}</th><th class="py-2 pr-2 text-right">{{ __('Saving / extra cost') }}</th><th class="py-2"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($analysis['tenures'] as $tenure)
                                <tr @class(['bg-accent-soft/60' => $tenure['years'] === $newYears])>
                                    <td class="py-2 pr-2">
                                        <span class="font-medium text-ink">{{ trans_choice(':count year|:count years', $tenure['years']) }}</span>
                                        <span class="mt-0.5 flex flex-wrap gap-1">
                                            @if ($tenure['years'] === $analysis['best_years'])<span class="rounded-full bg-pass-soft px-1.5 text-[0.65rem] font-semibold text-pass">{{ __('Biggest saving') }}</span>@endif
                                            @if ($tenure['years'] === $analysis['within_today_years'])<span class="rounded-full bg-accent-soft px-1.5 text-[0.65rem] font-semibold text-accent">{{ __('Best below today’s EMI') }}</span>@endif
                                            @if ($tenure['years'] === $analysis['lowest_saving_emi_years'] && $tenure['years'] !== $analysis['best_years'])<span class="rounded-full bg-surface-2 px-1.5 text-[0.65rem] font-semibold text-ink-muted">{{ __('Lowest EMI that saves') }}</span>@endif
                                        </span>
                                    </td>
                                    <td @class(['py-2 pr-2 text-right font-mono', 'text-pass' => $tenure['emi'] <= $analysis['current_emi'], 'text-ink' => $tenure['emi'] > $analysis['current_emi']])>₹{{ $this->formatAmount($tenure['emi']) }}</td>
                                    <td @class(['py-2 pr-2 text-right font-mono font-semibold', 'text-pass' => $tenure['net'] > 0, 'text-warn' => $tenure['net'] <= 0])>{{ $tenure['net'] > 0 ? '+' : '−' }}₹{{ $this->formatAmount(abs($tenure['net'])) }}</td>
                                    <td class="py-2 text-right">
                                        @if ($tenure['years'] === $newYears)
                                            <span class="text-xs font-semibold text-accent">{{ __('Selected') }}</span>
                                        @else
                                            <button type="button" wire:click="useTenure({{ $tenure['years'] }})" class="rounded-full border border-line-strong px-2.5 py-0.5 text-xs font-medium text-accent hover:border-accent">{{ __('Use') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-ink-faint">{{ __('EMI in green is lower than what you pay today (₹:emi).', ['emi' => $this->formatAmount($analysis['current_emi'])]) }}</p>
            </div>

            {{-- Year by year: the chart grows to fill the card, which matches the tenure finder's height. --}}
            @php
                $yearlyPeak = max(1, ...array_map(fn ($year) => max($year['cumulative_existing'], $year['cumulative_proposed']), $analysis['yearly']));
                $lastYear = \Illuminate\Support\Arr::last($analysis['yearly']);
                $keepEnd = collect($analysis['yearly'])->first(fn ($year) => $year['cumulative_existing'] >= $lastYear['cumulative_existing']);
                $newEnd = collect($analysis['yearly'])->first(fn ($year) => $year['cumulative_proposed'] >= $lastYear['cumulative_proposed']);
            @endphp
            <div class="flex flex-col rounded-2xl border border-line bg-surface p-5 shadow-sm" data-yearly>
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('Year by year') }}</h2>
                <p class="mt-1 text-xs text-ink-muted">{{ __('Total repaid by the end of each year, from today.') }}</p>
                <div class="mt-5 flex min-h-48 flex-1 items-end gap-3 overflow-x-auto border-b border-line pb-1" data-yearly-chart>
                    @foreach ($analysis['yearly'] as $year)
                        <div class="flex h-full min-w-12 flex-1 items-end justify-center gap-1.5">
                            <div class="w-1/2 max-w-8 rounded-t-md bg-ink-faint/40 transition-all duration-500" style="height: {{ max(1, round($year['cumulative_existing'] / $yearlyPeak * 100, 1)) }}%" title="{{ __('Keep as is') }}: ₹{{ $this->formatAmount($year['cumulative_existing']) }}"></div>
                            <div class="w-1/2 max-w-8 rounded-t-md bg-accent transition-all duration-500" style="height: {{ max(1, round($year['cumulative_proposed'] / $yearlyPeak * 100, 1)) }}%" title="{{ __('Consolidated') }}: ₹{{ $this->formatAmount($year['cumulative_proposed']) }}"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex gap-3 text-center text-[0.7rem] text-ink-muted">
                    @foreach ($analysis['yearly'] as $year)<span class="min-w-12 flex-1">{{ __('Yr :year', ['year' => $year['year']]) }}</span>@endforeach
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4 text-sm">
                    <div>
                        <dt class="flex items-center gap-1.5 text-xs text-ink-muted"><span class="inline-block h-2 w-2 rounded-full bg-ink-faint/60"></span>{{ __('Keep as is') }}</dt>
                        <dd class="mt-0.5 font-mono font-semibold text-ink">₹{{ $this->formatAmount($lastYear['cumulative_existing']) }}</dd>
                        <dd class="text-xs text-ink-muted">{{ __('repaid by year :year', ['year' => $keepEnd['year']]) }}</dd>
                    </div>
                    <div>
                        <dt class="flex items-center gap-1.5 text-xs text-ink-muted"><span class="inline-block h-2 w-2 rounded-full bg-accent"></span>{{ __('Consolidated') }}</dt>
                        <dd class="mt-0.5 font-mono font-semibold text-ink">₹{{ $this->formatAmount($lastYear['cumulative_proposed']) }}</dd>
                        <dd class="text-xs text-ink-muted">{{ __('repaid by year :year', ['year' => $newEnd['year']]) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Debts today --}}
            <div class="rounded-2xl border border-line bg-surface p-5 shadow-sm" data-debts>
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('Your debts if you keep them') }}</h2>
                <table class="mt-4 w-full text-sm">
                    <thead class="text-left text-[0.7rem] uppercase tracking-wide text-ink-faint"><tr><th class="py-2 pr-2"></th><th class="py-2 pr-2 text-right">{{ __('Each month') }}</th><th class="py-2 pr-2 text-right">{{ __('Clears in') }}</th><th class="py-2 text-right">{{ __('Interest left') }}</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($analysis['debts'] as $debt)
                            <tr>
                                <td class="py-2 pr-2 font-medium text-ink">{{ $debt['name'] }}</td>
                                <td class="py-2 pr-2 text-right font-mono">₹{{ $this->formatAmount($debt['monthly']) }}</td>
                                @if ($debt['assumed'])
                                    {{-- The payment does not cover the interest: the engine only projects
                                         a stand-in term to compare options, so no end date is shown. --}}
                                    <td class="py-2 pr-2 text-right font-semibold text-warn" data-never-clears>{{ __('Never at this payment') }}</td>
                                    <td class="py-2 text-right text-warn">{{ __('Keeps growing') }}</td>
                                @else
                                    <td class="py-2 pr-2 text-right">{{ trans_choice(':count month|:count months', $debt['months']) }}</td>
                                    <td class="py-2 text-right font-mono text-warn">₹{{ $this->formatAmount($debt['interest']) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-ink-faint">{{ __('Paying only the minimum due keeps most of each payment going to interest. Card statements must show how long the minimum alone would take.') }}</p>
            </div>

            {{-- Affordability --}}
            <div class="rounded-2xl border border-line bg-surface p-5 shadow-sm" data-affordability>
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('Share of income going to EMIs') }}</h2>
                @if ($analysis['affordability'] === null)
                    <p class="mt-2 text-sm text-ink-muted">{{ __('Add your net monthly income to see how much of it goes to EMIs today and after consolidating.') }}</p>
                @else
                    <div class="mt-4 flex flex-col gap-4">
                        @foreach ([[__('Today'), $analysis['affordability']['today'], $analysis['affordability']['today_band'], 'bg-ink-faint/60'], [__('After consolidating'), $analysis['affordability']['after'], $analysis['affordability']['after_band'], 'bg-accent']] as [$rowLabel, $value, $band, $barClass])
                            <div>
                                <div class="flex justify-between text-sm"><span class="text-ink-muted">{{ $rowLabel }}</span><span class="font-semibold text-ink">{{ round((float) $value, 1) }}% · {{ $band }}</span></div>
                                <div class="relative mt-1.5 h-2.5 rounded-full bg-surface-2">
                                    <div class="h-2.5 rounded-full transition-all duration-500 {{ $barClass }}" style="width: {{ min(100, max(0, round((float) $value, 1))) }}%"></div>
                                    <span class="absolute inset-y-0 left-[40%] w-px bg-pass" aria-hidden="true"></span>
                                    <span class="absolute inset-y-0 left-[50%] w-px bg-warn" aria-hidden="true"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-ink-faint">{{ __('Markers at 40% and 50%: most lenders prefer EMIs within this share of take-home income.') }}</p>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-4 rounded-2xl border border-line bg-surface-2 p-5 sm:flex-row sm:items-center">
            <p class="flex-1 text-sm font-semibold text-ink">{{ __('Total debt to consolidate: ₹:amount', ['amount' => $this->formatAmount($analysis['total_debt'])]) }} <span class="block text-xs font-normal text-ink-muted">{{ __('New loan, incl. fee and charges') }}: ₹{{ $this->formatAmount($analysis['loan_amount']) }}</span></p>
            <div class="flex flex-wrap items-center gap-3" x-data="{ copied: false }">
                <button type="button" x-on:click="navigator.clipboard?.writeText(@js($this->shareUrl)); copied = true; setTimeout(() => copied = false, 2000)" class="rounded-full border border-line-strong px-4 py-2 text-sm font-medium text-ink hover:border-accent hover:text-accent" data-share>
                    <span x-show="! copied">{{ __('Copy link to these figures') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Link copied') }}</span>
                </button>
                <x-ui.button tag="a" :href="$this->nextStep['url']">{{ $this->nextStep['label'] }}</x-ui.button>
            </div>
        </div>

        <p class="text-xs text-ink-faint">{{ __('Figures are estimates from the details you enter, using the same method FynnEdge advisors use. Your card payment is assumed to stay the same each month. The processing fee and its :gst% GST are taken from the new loan, and any foreclosure charge (plus GST) is added to it. Lenders decide the actual rate, fees and eligibility. A lower EMI does not by itself mean you save money — compare the total cost.', ['gst' => rtrim(rtrim(number_format((float) \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::get('gst_rate_percent'), 2), '0'), '.')]) }}</p>
    @endif
</div>
