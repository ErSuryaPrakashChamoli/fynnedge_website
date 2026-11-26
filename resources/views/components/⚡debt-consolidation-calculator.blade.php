<?php

use App\Modules\DebtConsolidation\DataTransferObjects\BusinessFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\CaseAssessment;
use App\Modules\DebtConsolidation\DataTransferObjects\CaseInput;
use App\Modules\DebtConsolidation\DataTransferObjects\IndividualFinances;
use App\Modules\DebtConsolidation\DataTransferObjects\LiabilityInput;
use App\Modules\DebtConsolidation\DataTransferObjects\ScenarioInput;
use App\Modules\DebtConsolidation\Enums\ActorType;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Models\DebtCalculationSnapshot;
use App\Modules\DebtConsolidation\Services\CaseInputBuilder;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings;
use App\Modules\DebtConsolidation\Services\Finance\DebtConsolidationEngine;
use App\Modules\DebtConsolidation\Services\LiabilityRows;
use App\Support\Pages\DebtConsolidationPages;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Debt Consolidation calculator suite: the visitor enters their loans
 * once and switches between six views of the same central engine result —
 * consolidation, foreclosure cost, side-by-side comparison, affordability,
 * break-even and portfolio summary. Nothing is stored while they type; a
 * snapshot is saved only when they carry the figures into an assessment.
 */
new class extends Component
{
    public string $applicantType = 'individual';

    public string $tab = 'consolidation';

    public int $selectedScenario = 0;

    /** @var array<int, array<string, string>> */
    public array $liabilities = [];

    /** @var array<int, array<string, string>> */
    public array $scenarios = [];

    /** @var array<string, string> */
    public array $finances = [
        'net_monthly_income' => '',
        'other_monthly_income' => '',
        'monthly_living_expenses' => '',
        'other_monthly_obligations' => '',
        'average_monthly_revenue' => '',
        'monthly_operating_expenses' => '',
    ];

    public function mount(): void
    {
        $types = DebtConsolidationSettings::availableApplicantTypes();
        $this->applicantType = ($types[0] ?? ApplicantType::Individual)->value;
        $this->tab = DebtConsolidationSettings::calculators()[0] ?? 'consolidation';
        $this->resetForApplicant();
    }

    public function updatedApplicantType(): void
    {
        $this->resetForApplicant();
    }

    /**
     * The add button stops at MAX_ROWS; a request that sends more is cut back
     * before anything renders.
     */
    public function updatedLiabilities(): void
    {
        $this->liabilities = LiabilityRows::limit($this->liabilities);
    }

    public function updatedScenarios(): void
    {
        $this->scenarios = array_slice(array_values($this->scenarios), 0, LiabilityRows::MAX_SCENARIOS);
    }

    public function addLiability(): void
    {
        if (count($this->liabilities) < LiabilityRows::MAX_ROWS) {
            $this->liabilities[] = LiabilityRows::blank($this->applicant());
        }
    }

    public function removeLiability(int $index): void
    {
        unset($this->liabilities[$index]);
        $this->liabilities = array_values($this->liabilities);
    }

    public function addScenario(): void
    {
        if (count($this->scenarios) < LiabilityRows::MAX_SCENARIOS) {
            $this->scenarios[] = LiabilityRows::blankScenario(__('Option :number', ['number' => count($this->scenarios) + 1]), 13, 60, 2);
        }
    }

    public function removeScenario(int $index): void
    {
        unset($this->scenarios[$index]);
        $this->scenarios = array_values($this->scenarios);
        $this->selectedScenario = 0;
    }

    public function showTab(string $tab): void
    {
        if (in_array($tab, DebtConsolidationSettings::calculators(), true)) {
            $this->tab = $tab;
        }
    }

    #[Computed]
    public function assessment(): ?CaseAssessment
    {
        $liabilities = LiabilityRows::toInputs($this->liabilities);
        $scenarios = LiabilityRows::scenarioInputs($this->scenarios);

        if ($liabilities === []) {
            return null;
        }

        return app(DebtConsolidationEngine::class)->assess($this->caseInput($liabilities, $scenarios));
    }

    /**
     * The calculation exactly as shown: the debts, the options and the income
     * figures the visitor entered (also what a saved snapshot stores).
     *
     * @param  list<LiabilityInput>  $liabilities
     * @param  list<ScenarioInput>  $scenarios
     */
    private function caseInput(array $liabilities, array $scenarios): CaseInput
    {
        return app(CaseInputBuilder::class)->forCalculator(
            $this->applicant(),
            $liabilities,
            $scenarios,
            new IndividualFinances(
                $this->number('net_monthly_income'),
                $this->number('other_monthly_income') ?? 0.0,
                $this->number('monthly_living_expenses'),
                $this->number('other_monthly_obligations') ?? 0.0,
            ),
            new BusinessFinances($this->number('average_monthly_revenue'), $this->number('monthly_operating_expenses'), null, null, $this->number('other_monthly_obligations') ?? 0.0),
        );
    }

    /**
     * Where "get a free assessment" leads, only while that page can be reached.
     */
    #[Computed]
    public function assessmentUrl(): ?string
    {
        return DebtConsolidationSettings::acceptsApplicant($this->applicant()) ? DebtConsolidationPages::liveUrl('assessment') : null;
    }

    /**
     * Saves exactly what the visitor saw as a calculator snapshot and carries
     * the loans into the assessment form, which offers them after the OTP.
     */
    public function continueToAssessment(): void
    {
        if ($this->assessmentUrl === null) {
            $this->addError('liabilities', DebtConsolidationSettings::pausedMessage());

            return;
        }

        $this->validate(LiabilityRows::rules('liabilities', $this->applicant()), [], ['liabilities.*.outstanding' => __('outstanding principal')]);

        // Livewire actions bypass route throttles; each save stores a snapshot.
        $throttleKey = 'debt-calculator-save:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 20)) {
            $this->addError('liabilities', __('Too many attempts. Please try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60)]));

            return;
        }

        RateLimiter::hit($throttleKey, 3600);
        $liabilities = LiabilityRows::toInputs($this->liabilities);
        $case = $this->caseInput($liabilities, LiabilityRows::scenarioInputs($this->scenarios));
        $assessment = app(DebtConsolidationEngine::class)->assess($case);

        $snapshot = DebtCalculationSnapshot::query()->create([
            'kind' => DebtCalculationSnapshot::KIND_CALCULATOR,
            'formula_version' => $assessment->formulaVersion,
            'scoring_version' => $case->scoringConfig->version,
            'inputs' => $case->toArray(),
            'inputs_hash' => $case->hash(),
            'results' => $assessment->toArray(),
            'total_outstanding' => $assessment->portfolio->totalOutstanding,
            'created_by_type' => ActorType::Customer,
        ]);

        session()->put('debt_consolidation.prefill', [
            'applicant_type' => $this->applicantType,
            'liabilities' => array_values($this->liabilities),
            'finances' => $this->finances,
            'snapshot' => $snapshot->public_id,
        ]);

        $this->redirect($this->assessmentUrl);
    }

    /**
     * @return list<LiabilityType>
     */
    #[Computed]
    public function liabilityTypes(): array
    {
        return LiabilityType::forApplicant($this->applicant());
    }

    private function resetForApplicant(): void
    {
        $applicant = $this->applicant();
        $this->liabilities = [LiabilityRows::blank($applicant)];
        $this->scenarios = array_map(
            fn (array $row): array => LiabilityRows::blankScenario($row['label'], $row['annual_interest_rate'], $row['tenure_months'], $row['processing_fee_percent'], $row['route']),
            array_slice(DebtConsolidationSettings::standardScenarios($applicant), 0, 2),
        );

        if ($this->scenarios === []) {
            $this->addScenario();
        }

        $this->selectedScenario = 0;
    }

    private function applicant(): ApplicantType
    {
        $type = ApplicantType::tryFrom($this->applicantType) ?? ApplicantType::Individual;

        return DebtConsolidationSettings::acceptsApplicant($type) ? $type : (DebtConsolidationSettings::availableApplicantTypes()[0] ?? ApplicantType::Individual);
    }

    private function number(string $key): ?float
    {
        $value = str_replace([',', '₹', ' '], '', (string) ($this->finances[$key] ?? ''));

        return is_numeric($value) ? max(0.0, (float) $value) : null;
    }
};
?>

@php
    use App\Modules\DebtConsolidation\Services\DebtPresenter as P;

    $assessment = $this->assessment;
    $calculators = \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::calculators();
    $tabLabels = [
        'consolidation' => __('Consolidation'),
        'foreclosure' => __('Foreclosure cost'),
        'comparison' => __('EMI & savings comparison'),
        'affordability' => __('Affordability'),
        'break_even' => __('Break-even'),
        'portfolio' => __('Portfolio summary'),
    ];
    // What each tab will show, so a tab clicked before any loan is entered still answers.
    $tabHints = [
        'consolidation' => __('What you would repay in total if you keep your loans, compared with each consolidation option.'),
        'foreclosure' => __('What it costs to close each loan today: principal, dues, charges and GST.'),
        'comparison' => __('Monthly outgo, total repayment and interest, side by side.'),
        'affordability' => __('How much of your income goes to loan repayments, now and after consolidating.'),
        'break_even' => __('When the cost of consolidating is paid back by the interest you save.'),
        'portfolio' => __('Your loans at a glance: total debt, rates, lenders and when they end.'),
    ];
    $scenarioRow = $assessment?->scenarios[min($selectedScenario, max(0, count($assessment->scenarios) - 1))] ?? null;
    $result = $scenarioRow['result'] ?? null;
    $names = P::liabilityNames(array_map(fn ($row) => ['key' => $row['key'], 'lender_name' => $row['lender_name'], 'type' => $row['type']], $liabilities));
    $input = 'mt-1 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30';
    $applicantTypes = \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::availableApplicantTypes();
@endphp

<div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:items-start">
    {{-- Inputs --}}
    <div class="flex flex-col gap-6">
        @if (count($applicantTypes) > 1)
            <div class="inline-flex self-start rounded-full border border-line bg-surface p-1" role="radiogroup" aria-label="{{ __('Calculate for') }}">
                @foreach ($applicantTypes as $type)
                    <label @class(['cursor-pointer rounded-full px-4 py-1.5 text-sm font-medium', 'bg-accent text-white' => $applicantType === $type->value, 'text-ink-muted' => $applicantType !== $type->value])>
                        <input type="radio" class="sr-only" value="{{ $type->value }}" wire:model.live="applicantType">
                        {{ $type === \App\Modules\DebtConsolidation\Enums\ApplicantType::Business ? __('Business') : __('Personal') }}
                    </label>
                @endforeach
            </div>
        @endif

        <div>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Your existing loans and cards') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ __('Use the figures on your latest statements. If you know the EMI and the months left, enter both — they give the most accurate result.') }}</p>
            <div class="mt-4">
                <x-debt.liability-editor :rows="$liabilities" :types="$this->liabilityTypes" model="liabilities" />
            </div>
        </div>

        <div>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Consolidation loan options') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ __('Indicative terms you can change. Lenders set the actual rate and fees.') }}</p>
            <div class="mt-4 flex flex-col gap-3">
                @foreach ($scenarios as $index => $scenario)
                    <fieldset wire:key="scenario-{{ $scenario['key'] }}" class="rounded-2xl border border-line bg-surface p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <input type="text" wire:model.live.debounce.500ms="scenarios.{{ $index }}.label" maxlength="60" class="min-w-0 flex-1 border-0 bg-transparent p-0 font-display text-sm font-semibold text-ink focus:ring-0" aria-label="{{ __('Option name') }}">
                            @if (count($scenarios) > 1)
                                <button type="button" wire:click="removeScenario({{ $index }})" class="text-xs font-medium text-warn hover:underline">{{ __('Remove') }}</button>
                            @endif
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <label class="block"><span class="text-xs font-medium text-ink-muted">{{ __('Rate (% a year)') }}</span><input type="text" inputmode="decimal" wire:model.live.debounce.500ms="scenarios.{{ $index }}.rate" class="{{ $input }}"></label>
                            <label class="block"><span class="text-xs font-medium text-ink-muted">{{ __('Tenure (months)') }}</span><input type="text" inputmode="numeric" wire:model.live.debounce.500ms="scenarios.{{ $index }}.tenure" class="{{ $input }}"></label>
                            <label class="block"><span class="text-xs font-medium text-ink-muted">{{ __('Processing fee (%)') }}</span><input type="text" inputmode="decimal" wire:model.live.debounce.500ms="scenarios.{{ $index }}.fee_percent" class="{{ $input }}"></label>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-ink-muted">
                            <label class="flex items-center gap-2"><input type="checkbox" value="1" wire:model.live="scenarios.{{ $index }}.fees_deducted" true-value="1" false-value="0" class="h-4 w-4 rounded border-line-strong text-accent"> {{ __('Fee deducted from the loan') }}</label>
                            <label class="flex items-center gap-2"><input type="checkbox" value="1" wire:model.live="scenarios.{{ $index }}.closure_financed" true-value="1" false-value="0" class="h-4 w-4 rounded border-line-strong text-accent"> {{ __('Foreclosure charges paid from the loan') }}</label>
                        </div>
                    </fieldset>
                @endforeach
                @if (count($scenarios) < \App\Modules\DebtConsolidation\Services\LiabilityRows::MAX_SCENARIOS)
                    <button type="button" wire:click="addScenario" class="self-start rounded-full border border-dashed border-accent px-4 py-2 text-sm font-semibold text-accent hover:bg-accent-soft">+ {{ __('Compare another option') }}</button>
                @endif
            </div>
        </div>

        @if (in_array('affordability', $calculators, true))
            <div>
                <h2 class="font-display text-lg font-semibold text-ink">{{ $applicantType === 'business' ? __('Business cash flow (optional)') : __('Your monthly income (optional)') }}</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @if ($applicantType === 'business')
                        <label class="block"><span class="text-xs font-medium text-ink-muted">{{ __('Average monthly revenue (₹)') }}</span><input type="text" inputmode="decimal" wire:model.live.debounce.500ms="finances.average_monthly_revenue" class="{{ $input }}"></label>
                        <label class="block"><span class="text-xs font-medium text-ink-muted">{{ __('Monthly operating expenses, excluding loan repayments (₹)') }}</span><input type="text" inputmode="decimal" wire:model.live.debounce.500ms="finances.monthly_operating_expenses" class="{{ $input }}"></label>
                    @else
                        <label class="block"><span class="text-xs font-medium text-ink-muted">{{ __('Net monthly income (₹)') }}</span><input type="text" inputmode="decimal" wire:model.live.debounce.500ms="finances.net_monthly_income" class="{{ $input }}"></label>
                        <label class="block"><span class="text-xs font-medium text-ink-muted">{{ __('Other monthly income (₹)') }}</span><input type="text" inputmode="decimal" wire:model.live.debounce.500ms="finances.other_monthly_income" class="{{ $input }}"></label>
                        <label class="block"><span class="text-xs font-medium text-ink-muted">{{ __('Monthly living expenses (₹)') }}</span><input type="text" inputmode="decimal" wire:model.live.debounce.500ms="finances.monthly_living_expenses" class="{{ $input }}"></label>
                    @endif
                    <label class="block"><span class="text-xs font-medium text-ink-muted">{{ __('Other fixed obligations (₹ a month)') }}</span><input type="text" inputmode="decimal" wire:model.live.debounce.500ms="finances.other_monthly_obligations" class="{{ $input }}"></label>
                </div>
            </div>
        @endif
    </div>

    {{-- Results --}}
    {{--
        Desktop: the results stick below the sticky site header (top-below-header; at top-6 the
        first row of tabs slid under it) and are capped at the screen's height, scrolling on their
        own with the tab bar pinned, so a long result can never push the tabs out of reach.
    --}}
    <div class="flex flex-col gap-4 lg:sticky lg:top-below-header lg:max-h-[calc(100vh-var(--site-header-height)-2rem)] lg:overflow-y-auto lg:pb-1" data-calculator-results>
        <div class="flex flex-wrap gap-1 rounded-2xl border border-line bg-surface p-1 lg:sticky lg:top-0 lg:z-10" role="tablist">
            @foreach ($calculators as $key)
                <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="showTab('{{ $key }}')" @class(['shrink-0 rounded-xl px-3 py-2 text-xs font-semibold sm:text-sm', 'bg-accent text-white' => $tab === $key, 'text-ink-muted hover:text-ink' => $tab !== $key])>{{ $tabLabels[$key] }}</button>
            @endforeach
        </div>

        <div class="rounded-2xl border border-line bg-surface p-5 shadow-sm" wire:loading.class="opacity-60" aria-live="polite">
            @if ($assessment === null)
                <div data-calculator-empty>
                    <p class="font-semibold text-ink">{{ $tabLabels[$tab] ?? '' }}</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ $tabHints[$tab] ?? '' }}</p>
                    <p class="mt-3 text-sm text-ink-muted">{{ __('Enter the outstanding amount of at least one loan to see your results.') }}</p>
                    <button type="button" x-on:click="const field = document.querySelector('[data-first-outstanding]'); field?.scrollIntoView({ block: 'center' }); field?.focus({ preventScroll: true })" class="mt-4 rounded-full border border-accent px-4 py-2 text-sm font-semibold text-accent hover:bg-accent-soft">{{ __('Enter my first loan') }}</button>
                </div>
            @else
                @if (count($assessment->scenarios) > 1 && in_array($tab, ['consolidation', 'break_even'], true))
                    <label class="mb-4 block text-xs font-medium text-ink-muted">{{ __('Showing') }}
                        <select wire:model.live="selectedScenario" class="ml-2 rounded-lg border border-line-strong bg-surface px-2 py-1 text-sm text-ink">
                            @foreach ($assessment->scenarios as $index => $row)
                                <option value="{{ $index }}">{{ __($row['scenario']->label) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if ($tab === 'consolidation' && $result)
                    <div @class(['rounded-xl p-4', 'bg-pass-soft' => $result->netFinancialBenefit > 0, 'bg-warn-soft' => $result->netFinancialBenefit <= 0])>
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ $result->netFinancialBenefit > 0 ? __('Estimated net saving') : __('Estimated extra cost') }}</p>
                        <p class="mt-1 font-display text-3xl font-semibold text-ink">{{ P::inr(abs($result->netFinancialBenefit)) }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ __('Total you would repay from today, existing vs consolidated, after all fees and charges.') }}</p>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        @foreach ([
                            [__('Total existing outstanding'), P::inr($result->principalClosed)],
                            [__('Existing total EMI'), P::inr($result->existingMonthlyOutflow)],
                            [__('Remaining repayment (existing)'), P::inr($result->existingTotalOutflow)],
                            [__('Existing interest cost'), P::inr($result->existingInterest)],
                            [__('New loan amount'), P::inr($result->loanAmount)],
                            [__('Proposed EMI'), P::inr($result->newEmi)],
                            [__('Proposed total repayment'), P::inr($result->proposedTotalOutflow)],
                            [__('Proposed interest cost'), P::inr($result->proposedInterest)],
                            [__('Fees and charges'), P::inr($result->totalConsolidationCosts)],
                            [__('Monthly cash-flow change'), ($result->monthlyCashFlowDifference >= 0 ? __(':amount less', ['amount' => P::inr($result->monthlyCashFlowDifference)]) : __(':amount more', ['amount' => P::inr(abs($result->monthlyCashFlowDifference))]))],
                            [__('Repayment period'), P::months($result->existingMonths).' → '.P::months($result->proposedMonths)],
                            [__('Average rate'), P::percent($result->weightedExistingRate).' → '.P::percent($result->annualInterestRate)],
                        ] as [$term, $value])
                            <div><dt class="text-xs text-ink-muted">{{ $term }}</dt><dd class="font-semibold text-ink">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    @php $suitability = $scenarioRow['suitability']; @endphp
                    <div class="mt-4 rounded-xl border border-line p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ __('Suitability score') }}</p>
                        <p class="mt-1 text-lg font-semibold text-ink">{{ number_format($suitability->score, 0) }}/100 · {{ $suitability->band->getLabel() }}</p>
                        <p class="mt-1 text-xs text-ink-muted">{{ \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::text('suitability_disclaimer') }}</p>
                    </div>
                @elseif ($tab === 'foreclosure')
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[34rem] text-left text-sm">
                            <thead class="text-xs text-ink-muted"><tr><th class="py-2 pr-3">{{ __('Loan') }}</th><th class="py-2 pr-3 text-right">{{ __('Principal') }}</th><th class="py-2 pr-3 text-right">{{ __('Dues') }}</th><th class="py-2 pr-3 text-right">{{ __('Charges + GST') }}</th><th class="py-2 text-right">{{ __('To close') }}</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @php $totals = ['principal' => 0, 'dues' => 0, 'costs' => 0, 'total' => 0]; @endphp
                                @foreach ($assessment->liabilities as $key => $row)
                                    @if ($row['full_closure'])
                                        @php $closure = $row['full_closure']; @endphp
                                        @php $totals = ['principal' => $totals['principal'] + $closure->principal, 'dues' => $totals['dues'] + $closure->dues(), 'costs' => $totals['costs'] + $closure->closureCosts(), 'total' => $totals['total'] + $closure->total]; @endphp
                                        <tr><td class="py-2 pr-3 text-ink">{{ $names[$key] ?? $key }}</td><td class="py-2 pr-3 text-right">{{ P::inr($closure->principal) }}</td><td class="py-2 pr-3 text-right">{{ P::inr($closure->dues()) }}</td><td class="py-2 pr-3 text-right">{{ P::inr($closure->closureCosts()) }}</td><td class="py-2 text-right font-semibold">{{ P::inr($closure->total) }}</td></tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-line font-semibold text-ink"><tr><td class="py-2 pr-3">{{ __('Total') }}</td><td class="py-2 pr-3 text-right">{{ P::inr($totals['principal']) }}</td><td class="py-2 pr-3 text-right">{{ P::inr($totals['dues']) }}</td><td class="py-2 pr-3 text-right">{{ P::inr($totals['costs']) }}</td><td class="py-2 text-right">{{ P::inr($totals['total']) }}</td></tr></tfoot>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-ink-muted">{{ __('Charges include :gst% GST. Dues are overdue amounts and instalments falling due before closure. Lender foreclosure quotations are usually valid for about :days days — the figure on the lender\'s letter is what you pay.', ['gst' => rtrim(rtrim(number_format((float) \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::get('gst_rate_percent'), 2), '0'), '.'), 'days' => \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::get('quotation_validity_days')]) }}</p>
                @elseif ($tab === 'comparison')
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[30rem] text-left text-sm">
                            <thead class="text-xs text-ink-muted"><tr><th class="py-2 pr-3"></th><th class="py-2 pr-3 text-right">{{ __('Keep as is') }}</th>@foreach ($assessment->scenarios as $row)<th class="py-2 pr-3 text-right">{{ __($row['scenario']->label) }}</th>@endforeach</tr></thead>
                            <tbody class="divide-y divide-line">
                                @php $first = $assessment->scenarios[0]['result'] ?? null; @endphp
                                <tr><td class="py-2 pr-3 text-ink-muted">{{ __('Monthly outgo') }}</td><td class="py-2 pr-3 text-right">{{ P::inr($first?->existingMonthlyOutflow) }}</td>@foreach ($assessment->scenarios as $row)<td class="py-2 pr-3 text-right">{{ P::inr($row['result']->proposedMonthlyOutflow) }}</td>@endforeach</tr>
                                <tr><td class="py-2 pr-3 text-ink-muted">{{ __('Total repayment') }}</td><td class="py-2 pr-3 text-right">{{ P::inr($first?->existingTotalOutflow) }}</td>@foreach ($assessment->scenarios as $row)<td class="py-2 pr-3 text-right">{{ P::inr($row['result']->proposedTotalOutflow) }}</td>@endforeach</tr>
                                <tr><td class="py-2 pr-3 text-ink-muted">{{ __('Interest') }}</td><td class="py-2 pr-3 text-right">{{ P::inr($first?->existingInterest) }}</td>@foreach ($assessment->scenarios as $row)<td class="py-2 pr-3 text-right">{{ P::inr($row['result']->proposedInterest) }}</td>@endforeach</tr>
                                <tr><td class="py-2 pr-3 text-ink-muted">{{ __('Fees and charges') }}</td><td class="py-2 pr-3 text-right">{{ P::inr(0) }}</td>@foreach ($assessment->scenarios as $row)<td class="py-2 pr-3 text-right">{{ P::inr($row['result']->totalConsolidationCosts) }}</td>@endforeach</tr>
                                <tr><td class="py-2 pr-3 text-ink-muted">{{ __('Debt-free in') }}</td><td class="py-2 pr-3 text-right">{{ P::months($first?->existingMonths) }}</td>@foreach ($assessment->scenarios as $row)<td class="py-2 pr-3 text-right">{{ P::months($row['result']->proposedMonths) }}</td>@endforeach</tr>
                                <tr class="font-semibold"><td class="py-2 pr-3 text-ink">{{ __('Net saving') }}</td><td class="py-2 pr-3 text-right">—</td>@foreach ($assessment->scenarios as $row)<td @class(['py-2 pr-3 text-right', 'text-pass' => $row['result']->netFinancialBenefit > 0, 'text-warn' => $row['result']->netFinancialBenefit <= 0])>{{ P::inr($row['result']->netFinancialBenefit) }}</td>@endforeach</tr>
                                <tr><td class="py-2 pr-3 text-ink-muted">{{ __('Suitability') }}</td><td class="py-2 pr-3 text-right">—</td>@foreach ($assessment->scenarios as $row)<td class="py-2 pr-3 text-right text-xs">{{ $row['suitability']->band->getLabel() }}</td>@endforeach</tr>
                            </tbody>
                        </table>
                    </div>
                @elseif ($tab === 'affordability')
                    @php $affordability = ($assessment->scenario() ?? $assessment->scenarios[0] ?? null)['affordability'] ?? $assessment->currentAffordability; @endphp
                    @if ($affordability->income === null)
                        <p class="text-sm text-ink-muted">{{ $applicantType === 'business' ? __('Enter your average monthly revenue and operating expenses to see your debt-service cover.') : __('Enter your net monthly income to see how much of it goes to loan repayments (FOIR).') }}</p>
                    @else
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div><dt class="text-xs text-ink-muted">{{ $affordability->metric === 'foir' ? __('FOIR today') : __('Debt-service cover today') }}</dt><dd class="text-2xl font-semibold text-ink">{{ $affordability->metric === 'foir' ? P::percent($affordability->currentValue) : ($affordability->currentValue === null ? '—' : $affordability->currentValue.'×') }}</dd><dd class="text-xs text-ink-muted">{{ $affordability->currentBand->getLabel() }}</dd></div>
                            <div><dt class="text-xs text-ink-muted">{{ __('After consolidating') }}</dt><dd class="text-2xl font-semibold text-ink">{{ $affordability->metric === 'foir' ? P::percent($affordability->proposedValue) : ($affordability->proposedValue === null ? '—' : $affordability->proposedValue.'×') }}</dd><dd class="text-xs text-ink-muted">{{ $affordability->proposedBand->getLabel() }}</dd></div>
                            <div><dt class="text-xs text-ink-muted">{{ __('Debt repayments today') }}</dt><dd class="font-semibold text-ink">{{ P::inr($affordability->currentDebtService) }}</dd></div>
                            <div><dt class="text-xs text-ink-muted">{{ __('After consolidating') }}</dt><dd class="font-semibold text-ink">{{ P::inr($affordability->proposedDebtService) }}</dd></div>
                            @if ($affordability->disposableAfter !== null)
                                <div><dt class="text-xs text-ink-muted">{{ $affordability->metric === 'foir' ? __('Left after expenses today') : __('Cash left after repayments today') }}</dt><dd class="font-semibold text-ink">{{ P::inr($affordability->disposableBefore) }}</dd></div>
                                <div><dt class="text-xs text-ink-muted">{{ __('After consolidating') }}</dt><dd class="font-semibold text-ink">{{ P::inr($affordability->disposableAfter) }}</dd></div>
                            @endif
                        </dl>
                        <p class="mt-3 text-xs text-ink-muted">{{ $affordability->metric === 'foir' ? __('FOIR is the share of your income that goes to loan repayments. Many lenders prefer it below 50–60%.') : __('Debt-service cover is your monthly cash flow divided by loan repayments. Lenders usually look for at least 1.2–1.5×.') }}</p>
                    @endif
                @elseif ($tab === 'break_even' && $result)
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-xs text-ink-muted">{{ __('Costs recovered by interest saved') }}</dt><dd class="text-xl font-semibold text-ink">{{ $result->costBreakEvenMonth === null ? __('Not recovered') : ($result->costBreakEvenMonth === 0 ? __('Immediately') : __('In month :month', ['month' => $result->costBreakEvenMonth])) }}</dd></div>
                        <div><dt class="text-xs text-ink-muted">{{ __('Consolidation costs') }}</dt><dd class="text-xl font-semibold text-ink">{{ P::inr($result->totalConsolidationCosts) }}</dd></div>
                    </dl>
                    @if ($result->costBreakEvenMonth !== null && ! $result->costRecoveryHolds)
                        <p class="mt-3 rounded-lg bg-warn-soft p-3 text-xs text-ink">{{ __('The early saving does not last: over the full, longer repayment you pay more interest than the costs saved.') }}</p>
                    @endif
                    <div class="mt-4 flex flex-col gap-2" aria-label="{{ __('Cumulative repayment by year') }}">
                        @php $max = max(1, ...array_map(fn ($year) => max($year['cumulative_existing'], $year['cumulative_proposed']), $result->yearly)); @endphp
                        @foreach ($result->yearly as $year)
                            <div class="text-xs">
                                <div class="flex justify-between text-ink-muted"><span>{{ __('Year :year', ['year' => $year['year']]) }}</span><span>{{ P::inr($year['cumulative_existing']) }} · {{ P::inr($year['cumulative_proposed']) }}</span></div>
                                <div class="mt-1 h-1.5 rounded-full bg-surface-2"><div class="h-1.5 rounded-full bg-ink-faint" style="width: {{ round($year['cumulative_existing'] / $max * 100, 1) }}%"></div></div>
                                <div class="mt-0.5 h-1.5 rounded-full bg-surface-2"><div class="h-1.5 rounded-full bg-accent" style="width: {{ round($year['cumulative_proposed'] / $max * 100, 1) }}%"></div></div>
                            </div>
                        @endforeach
                        <p class="text-xs text-ink-muted"><span class="inline-block h-2 w-2 rounded-full bg-ink-faint"></span> {{ __('Keep as is') }} · <span class="inline-block h-2 w-2 rounded-full bg-accent"></span> {{ __('Consolidated') }}</p>
                    </div>
                @elseif ($tab === 'portfolio')
                    @php $portfolio = $assessment->portfolio; @endphp
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        @foreach ([
                            [__('Total debt'), P::inr($portfolio->totalOutstanding)],
                            [__('Number of loans'), (string) $portfolio->liabilityCount],
                            [__('Total monthly repayments'), P::inr($portfolio->totalMonthlyObligation)],
                            [__('Weighted average rate'), P::percent($portfolio->weightedAverageRate)],
                            [__('Secured'), P::inr($portfolio->securedOutstanding).' ('.P::percent($portfolio->securedShare ?? 0).')'],
                            [__('Unsecured'), P::inr($portfolio->unsecuredOutstanding)],
                            [__('Remaining interest'), P::inr($portfolio->remainingInterest)],
                            [__('Largest lender share'), P::percent($portfolio->largestLenderShare)],
                        ] as [$term, $value])
                            <div><dt class="text-xs text-ink-muted">{{ $term }}</dt><dd class="font-semibold text-ink">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ __('When your loans end') }}</h3>
                    <ul class="mt-2 grid grid-cols-5 gap-1 text-center text-xs">
                        @foreach (['up_to_12' => __('≤1 yr'), '13_to_36' => __('1–3 yrs'), '37_to_60' => __('3–5 yrs'), '61_to_120' => __('5–10 yrs'), 'over_120' => __('10+ yrs')] as $bucket => $bucketLabel)
                            <li class="rounded-lg bg-surface-2 p-2"><span class="block font-semibold text-ink">{{ $portfolio->maturityProfile[$bucket]['count'] }}</span><span class="text-ink-muted">{{ $bucketLabel }}</span></li>
                        @endforeach
                    </ul>
                @endif

                @php $flags = $scenarioRow ? array_map(fn ($flag) => $flag->toArray(), array_values(array_filter($scenarioRow['result']->flags, fn ($flag) => $flag->flag->severity() !== 'info'))) : []; @endphp
                @if ($flags !== [] && in_array($tab, ['consolidation', 'comparison', 'break_even'], true))
                    <ul class="mt-4 flex flex-col gap-2">
                        @foreach ($flags as $flag)
                            <li class="flex gap-2 rounded-lg bg-warn-soft p-3 text-xs text-ink"><span aria-hidden="true">⚠</span>{{ P::flagSentence($flag, $names) }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>

        @error('liabilities') <p class="text-sm text-warn">{{ $message }}</p> @enderror
        @if ($this->assessmentUrl)
            <x-ui.button type="button" wire:click="continueToAssessment" class="w-full justify-center" size="lg">{{ __('Save these figures and get a free assessment') }}</x-ui.button>
        @endif
        <p class="text-xs text-ink-faint">{{ \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::text('calculator_disclaimer') }}</p>
    </div>
</div>
