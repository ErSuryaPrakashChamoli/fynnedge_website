<?php

namespace App\Filament\Pages;

use App\Models\LoanProduct;
use App\Modules\Eligibility\Services\EligibilityEngine;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EligibilityTester extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Eligibility Tester';

    protected static ?string $title = 'Eligibility Tester';

    protected string $view = 'filament.pages.eligibility-tester';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * Livewire public properties must stay plain/serializable, so this holds arrays —
     * not the Eloquent models + enum-bearing DTOs evaluateAttributesForLoanProduct() returns.
     *
     * @var array<int, array{lender_name: string, status: string, status_label: string, foir: ?float, reasons: array<int, array{label: string, priority: string, passed: bool, customer_message: ?string}>}>|null
     */
    public ?array $results = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Sample profile')
                    ->description('Enter a hypothetical applicant to see which lenders they would qualify for, and why — without a real application.')
                    ->columns(3)
                    ->components([
                        Select::make('loan_product_id')
                            ->label('Loan product')
                            ->options(LoanProduct::query()->published()->pluck('name', 'id'))
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('age')->numeric()->required(),
                        TextInput::make('city')->required(),
                        Select::make('employment_type')
                            ->options(['salaried' => 'Salaried', 'self-employed' => 'Self-employed']),
                        TextInput::make('employer_name')
                            ->label('Employer name')
                            ->helperText('Matched against each lender\'s own employer categories.'),
                        TextInput::make('monthly_income')->numeric()->required(),
                        TextInput::make('other_monthly_income')->numeric()->default(0),
                        Select::make('has_existing_emis')
                            ->options(['yes' => 'Yes', 'no' => 'No'])
                            ->default('no')
                            ->live(),
                        TextInput::make('existing_emi_amount')
                            ->numeric()
                            ->default(0)
                            ->visible(fn ($get) => $get('has_existing_emis') === 'yes'),
                        TextInput::make('loan_amount_requested')->numeric()->required(),
                        TextInput::make('preferred_tenure_months')->numeric()->required(),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('evaluate')
                ->label('Evaluate')
                ->submit('evaluate'),
        ];
    }

    public function evaluate(EligibilityEngine $engine): void
    {
        $state = $this->form->getState();
        $loanProduct = LoanProduct::query()->findOrFail($state['loan_product_id']);

        $attributes = [
            'age' => $state['age'],
            'city' => $state['city'],
            'employment_type' => $state['employment_type'] ?? null,
            'employer_name' => $state['employer_name'] ?? null,
            'monthly_income' => $state['monthly_income'],
            'other_monthly_income' => $state['other_monthly_income'] ?? 0,
            'total_monthly_income' => (float) $state['monthly_income'] + (float) ($state['other_monthly_income'] ?? 0),
            'has_existing_emis' => $state['has_existing_emis'] ?? 'no',
            'existing_emi_amount' => ($state['has_existing_emis'] ?? 'no') === 'yes' ? (float) ($state['existing_emi_amount'] ?? 0) : 0.0,
            'loan_amount_requested' => $state['loan_amount_requested'],
            'preferred_tenure_months' => $state['preferred_tenure_months'],
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
}
