<?php

use App\Enums\LoanCategory;
use App\Models\LoanProduct;
use App\Modules\Eligibility\Services\EligibilityEngine;
use App\Support\Options\CityOptions;
use App\Support\Options\EmployerOptions;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $category;

    public ?int $loanProductId = null;

    public string $loanProductSlug = '';

    public string $loanProductName = '';

    public ?int $age = null;

    public string $city = '';

    public string $employmentType = 'salaried';

    public string $employerName = '';

    public ?float $monthlyIncome = null;

    public float $otherMonthlyIncome = 0;

    public string $hasExistingEmis = 'no';

    public float $existingEmiAmount = 0;

    public ?float $loanAmountRequested = null;

    public ?int $preferredTenureMonths = null;

    public bool $creditConsent = false;

    /**
     * Livewire public properties must stay plain/serializable — see the note
     * on the same field in Filament's EligibilityTester page.
     *
     * @var array<int, array{lender_name: string, status: string, status_label: string, foir: ?float, reasons: array<int, array{label: string, priority: string, passed: bool, customer_message: ?string}>}>|null
     */
    public ?array $results = null;

    public function mount(string $category): void
    {
        $resolved = LoanCategory::tryFrom($category);
        $this->category = $resolved?->value ?? LoanCategory::PersonalLoan->value;

        $loanProduct = LoanProduct::query()->published()->where('category', $this->category)->first();
        $this->loanProductId = $loanProduct?->id;
        $this->loanProductSlug = $loanProduct?->slug ?? '';
        $this->loanProductName = $loanProduct?->name ?? '';
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function cityOptions(): array
    {
        return CityOptions::all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function employerOptions(): array
    {
        return EmployerOptions::all();
    }

    /**
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'age' => ['required', 'integer', 'min:18', 'max:80'],
            'city' => ['required', 'string'],
            'employmentType' => ['required', 'in:salaried,self-employed'],
            'monthlyIncome' => ['required', 'numeric', 'min:1'],
            'otherMonthlyIncome' => ['numeric', 'min:0'],
            'existingEmiAmount' => ['numeric', 'min:0'],
            'loanAmountRequested' => ['required', 'numeric', 'min:1'],
            'preferredTenureMonths' => ['required', 'integer', 'min:1'],
            'creditConsent' => ['accepted'],
        ];
    }

    public function evaluate(EligibilityEngine $engine): void
    {
        $this->validate();

        $loanProduct = LoanProduct::query()->findOrFail($this->loanProductId);

        $existingEmiAmount = $this->hasExistingEmis === 'yes' ? $this->existingEmiAmount : 0.0;

        $attributes = [
            'age' => $this->age,
            'city' => $this->city,
            'employment_type' => $this->employmentType,
            'employer_name' => $this->employerName ?: null,
            'monthly_income' => $this->monthlyIncome,
            'other_monthly_income' => $this->otherMonthlyIncome,
            'total_monthly_income' => (float) $this->monthlyIncome + (float) $this->otherMonthlyIncome,
            'has_existing_emis' => $this->hasExistingEmis,
            'existing_emi_amount' => $existingEmiAmount,
            'loan_amount_requested' => $this->loanAmountRequested,
            'preferred_tenure_months' => $this->preferredTenureMonths,
        ];

        $this->results = $engine->evaluateAttributesForLoanProduct($attributes, $loanProduct)
            ->map(fn (array $row) => [
                'lender_name' => $row['lenderProduct']->lender->name,
                'status' => $row['evaluation']->status->value,
                'status_label' => $row['evaluation']->status->getLabel(),
                'foir' => $row['evaluation']->foir,
                'reasons' => collect($row['evaluation']->reasons)->map(fn (array $reason) => [
                    'label' => $reason['label'],
                    'priority' => $reason['priority']->value,
                    'passed' => $reason['passed'],
                    'customer_message' => $reason['customer_message'],
                ])->all(),
            ])
            ->all();
    }
};
?>

<div>
    @if (! $loanProductId)
        <x-ui.alert tone="warn" title="Not available yet">
            This quick eligibility check isn't configured for this loan type yet. Try the full
            <a href="{{ route('eligibility.index') }}" class="font-medium underline">eligibility check</a> instead.
        </x-ui.alert>
    @else
        <form wire:submit="evaluate" class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="age" class="text-sm font-medium text-ink">Age</label>
                <input id="age" type="number" inputmode="numeric" wire:model="age"
                    class="mt-2 w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40">
                @error('age') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <x-ui.searchable-select name="city" label="City" :options="$this->cityOptions" />

            <div>
                <label for="employmentType" class="text-sm font-medium text-ink">Employment type</label>
                <select id="employmentType" wire:model="employmentType"
                    class="mt-2 w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40">
                    <option value="salaried">Salaried</option>
                    <option value="self-employed">Self-employed</option>
                </select>
            </div>

            <x-ui.searchable-select name="employerName" label="Employer name (optional)" :options="$this->employerOptions" />

            <div>
                <label for="monthlyIncome" class="text-sm font-medium text-ink">Monthly income (₹)</label>
                <input id="monthlyIncome" type="text" inputmode="numeric" autocomplete="off" wire:model="monthlyIncome"
                    wire:ignore.self
                    x-effect="const v = $wire.monthlyIncome; if (document.activeElement !== $el) $el.value = formatIndianNumber(String(v ?? ''))"
                    x-on:focus="$el.value = $el.value.replace(/[^0-9]/g, '')"
                    x-on:blur="$el.value = formatIndianNumber($el.value.replace(/[^0-9]/g, ''))"
                    class="mt-2 w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40">
                <p class="mt-1 text-xs text-ink-faint" x-show="$wire.monthlyIncome > 0" x-text="numberToIndianWords(String($wire.monthlyIncome)) + ' Rupees only'"></p>
                @error('monthlyIncome') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="otherMonthlyIncome" class="text-sm font-medium text-ink">Other monthly income (₹)</label>
                <input id="otherMonthlyIncome" type="text" inputmode="numeric" autocomplete="off" wire:model="otherMonthlyIncome"
                    wire:ignore.self
                    x-effect="const v = $wire.otherMonthlyIncome; if (document.activeElement !== $el) $el.value = formatIndianNumber(String(v ?? ''))"
                    x-on:focus="$el.value = $el.value.replace(/[^0-9]/g, '')"
                    x-on:blur="$el.value = formatIndianNumber($el.value.replace(/[^0-9]/g, ''))"
                    class="mt-2 w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40">
                <p class="mt-1 text-xs text-ink-faint" x-show="$wire.otherMonthlyIncome > 0" x-text="numberToIndianWords(String($wire.otherMonthlyIncome)) + ' Rupees only'"></p>
            </div>

            <div>
                <label for="hasExistingEmis" class="text-sm font-medium text-ink">Existing loan EMIs?</label>
                <select id="hasExistingEmis" wire:model.live="hasExistingEmis"
                    class="mt-2 w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40">
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                </select>
            </div>

            @if ($hasExistingEmis === 'yes')
                <div>
                    <label for="existingEmiAmount" class="text-sm font-medium text-ink">Existing EMI amount (₹)</label>
                    <input id="existingEmiAmount" type="text" inputmode="numeric" autocomplete="off" wire:model="existingEmiAmount"
                        wire:ignore.self
                        x-effect="const v = $wire.existingEmiAmount; if (document.activeElement !== $el) $el.value = formatIndianNumber(String(v ?? ''))"
                        x-on:focus="$el.value = $el.value.replace(/[^0-9]/g, '')"
                        x-on:blur="$el.value = formatIndianNumber($el.value.replace(/[^0-9]/g, ''))"
                        class="mt-2 w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40">
                    <p class="mt-1 text-xs text-ink-faint" x-show="$wire.existingEmiAmount > 0" x-text="numberToIndianWords(String($wire.existingEmiAmount)) + ' Rupees only'"></p>
                </div>
            @endif

            <div>
                <label for="loanAmountRequested" class="text-sm font-medium text-ink">Loan amount required (₹)</label>
                <input id="loanAmountRequested" type="text" inputmode="numeric" autocomplete="off" wire:model="loanAmountRequested"
                    wire:ignore.self
                    x-effect="const v = $wire.loanAmountRequested; if (document.activeElement !== $el) $el.value = formatIndianNumber(String(v ?? ''))"
                    x-on:focus="$el.value = $el.value.replace(/[^0-9]/g, '')"
                    x-on:blur="$el.value = formatIndianNumber($el.value.replace(/[^0-9]/g, ''))"
                    class="mt-2 w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40">
                <p class="mt-1 text-xs text-ink-faint" x-show="$wire.loanAmountRequested > 0" x-text="numberToIndianWords(String($wire.loanAmountRequested)) + ' Rupees only'"></p>
                @error('loanAmountRequested') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="preferredTenureMonths" class="text-sm font-medium text-ink">Preferred tenure (months)</label>
                <input id="preferredTenureMonths" type="number" inputmode="numeric" wire:model="preferredTenureMonths"
                    class="mt-2 w-full rounded-md border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent/40">
                @error('preferredTenureMonths') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="flex items-start gap-2.5 text-sm text-ink-muted">
                    <input type="checkbox" wire:model="creditConsent" class="mt-0.5 rounded border-line-strong text-accent focus:ring-accent/30">
                    <span>
                        By submitting this form, you have read and agree to the
                        <a href="{{ route('credit-report-terms') }}" target="_blank" rel="noopener" class="text-accent underline">Credit Report Terms of Use</a>,
                        <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="text-accent underline">Terms of Use</a>
                        &amp;
                        <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" class="text-accent underline">Privacy Policy</a>.
                    </span>
                </label>
                @error('creditConsent') <p class="mt-1 text-xs text-warn">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <x-ui.button type="submit">Check eligibility</x-ui.button>
            </div>
        </form>

        @if ($results !== null)
            <div class="mt-10 flex flex-col gap-4">
                <h2 class="font-display text-lg font-semibold text-ink">Results</h2>

                @if ($results === [])
                    <x-ui.alert tone="warn">No lenders are currently available for this loan type.</x-ui.alert>
                @endif

                @foreach ($results as $row)
                    <div class="rounded-xl border border-line bg-surface p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-display text-base font-semibold text-ink">{{ $row['lender_name'] }}</p>
                            <x-ui.badge :tone="$row['status'] === 'eligible' ? 'pass' : ($row['status'] === 'conditional' ? 'accent' : 'warn')">
                                {{ $row['status_label'] }}
                            </x-ui.badge>
                        </div>
                        @if ($row['foir'] !== null)
                            <p class="mt-2 text-sm text-ink-muted">FOIR: <span class="font-mono text-ink">{{ $row['foir'] }}%</span></p>
                        @endif
                        <ul class="mt-3 flex flex-col gap-1.5">
                            @foreach ($row['reasons'] as $reason)
                                <li class="flex items-start gap-2 text-sm">
                                    <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $reason['passed'] ? 'bg-pass' : 'bg-warn' }}"></span>
                                    <span class="text-ink-muted">{{ $reason['customer_message'] ?? $reason['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @php
                            $expertMessage = $row['status'] === 'eligible'
                                ? "I checked my eligibility for a {$loanProductName} and matched with {$row['lender_name']}. I'd like help understanding the next steps."
                                : "I checked my eligibility for a {$loanProductName} and didn't match with {$row['lender_name']}. I'd like help understanding my options.";
                        @endphp
                        <div class="mt-4 flex flex-wrap gap-3">
                            @if ($row['status'] === 'eligible' && $loanProductSlug)
                                <x-ui.button tag="a" :href="route('loans.apply', $loanProductSlug)" size="sm">
                                    Apply Now
                                </x-ui.button>
                            @endif
                            <x-ui.button tag="a" :href="route('contact', ['message' => $expertMessage])" size="sm" variant="secondary">
                                Talk to a FynnEdge Expert
                            </x-ui.button>
                        </div>
                    </div>
                @endforeach

                <x-ui.alert tone="accent">
                    This is an indicative, self-reported estimate. For a precise result and to proceed with an application,
                    <a href="{{ route('eligibility.index') }}" class="font-medium underline">check your full eligibility</a>.
                </x-ui.alert>
            </div>
        @endif
    @endif
</div>
