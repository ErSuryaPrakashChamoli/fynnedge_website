<?php

namespace App\Filament\Resources\DebtScenarios\Schemas;

use App\Filament\Resources\DebtApplications\Pages\ViewDebtApplication;
use App\Models\LenderProduct;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A consolidation scenario: the terms to compare against keeping the
 * existing loans. Choosing a lender offer fills in its published rate,
 * tenure and fee as a starting point; they remain assumptions until a
 * lender sanctions (then enter the sanctioned amount).
 */
class DebtScenarioForm
{
    public static function configure(Schema $schema, bool $withCase = false): Schema
    {
        return $schema->columns(3)->components(array_values(array_filter([
            $withCase ? Select::make('debt_application_id')->label('Case')->relationship('application', 'reference')->searchable()->required()->columnSpanFull() : null,
            TextInput::make('label')->required()->maxLength(120),
            Select::make('financing_route')->options(FinancingRoute::class),
            Select::make('lender_product_id')->label('Lender offer (optional)')->options(fn () => ViewDebtApplication::lenderProductOptions())->searchable()->live()
                ->afterStateUpdated(function ($state, callable $set): void {
                    $product = $state ? LenderProduct::query()->find($state) : null;

                    if ($product !== null) {
                        $set('annual_interest_rate', $product->interest_rate_from);
                        $set('tenure_months', $product->max_tenure_months);
                        $set('processing_fee_percent', $product->processing_fee_percent_max ?? $product->processing_fee_percent_min);
                    }
                }),
            Section::make('Terms')->columns(3)->columnSpanFull()->components([
                TextInput::make('annual_interest_rate')->label('Rate (% a year)')->numeric()->required()->minValue(0)->maxValue(60),
                TextInput::make('tenure_months')->numeric()->required()->minValue(1)->maxValue(360),
                TextInput::make('processing_fee_percent')->label('Processing fee %')->numeric()->minValue(0)->maxValue(10)->default(0),
                TextInput::make('processing_fee_fixed')->label('Fixed processing fee')->numeric()->prefix('₹')->minValue(0)->default(0),
                TextInput::make('other_upfront_costs')->label('Other costs (stamp duty, valuation…)')->numeric()->prefix('₹')->minValue(0)->default(0),
                TextInput::make('loan_amount_override')->label('Sanctioned loan amount')->numeric()->prefix('₹')->minValue(1)->helperText('Leave empty to size the loan to the closures.'),
                Toggle::make('fees_deducted_from_disbursal')->label('Fee deducted from payout')->default(true),
                Toggle::make('closure_costs_financed')->label('Foreclosure charges paid from the loan')->default(true),
                Toggle::make('is_preferred')->label('Preferred option'),
            ]),
        ])));
    }
}
