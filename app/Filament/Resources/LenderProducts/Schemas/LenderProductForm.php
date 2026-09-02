<?php

namespace App\Filament\Resources\LenderProducts\Schemas;

use App\Enums\EmploymentType;
use App\Enums\LenderStatus;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class LenderProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('lender_id')
                    ->relationship('lender', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),
                Select::make('loan_product_id')
                    ->label('Loan product')
                    ->relationship('loanProduct', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->unique(
                        table: 'lender_products',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('lender_id', $get('lender_id')),
                    )
                    ->validationMessages([
                        'unique' => 'This lender already has an offer for this loan product.',
                    ]),
                ...self::offerAndEligibilityComponents(),
            ]);
    }

    /**
     * The fields shared with LenderProductsRelationManager, which supplies
     * lender_id (and, being scoped to one loan product, loan_product_id)
     * itself rather than rendering pickers for them.
     *
     * @return array<int, Component>
     */
    public static function offerAndEligibilityComponents(): array
    {
        return [
            Select::make('status')
                ->options(LenderStatus::class)
                ->default(LenderStatus::Active)
                ->required(),
            TextInput::make('min_amount')->numeric()->prefix('₹'),
            TextInput::make('max_amount')->numeric()->prefix('₹'),
            TextInput::make('min_tenure_months')->numeric()->suffix('months'),
            TextInput::make('max_tenure_months')->numeric()->suffix('months'),
            TextInput::make('initial_tenure_months')
                ->label('Initial tenure (hybrid products only)')
                ->numeric()
                ->suffix('months')
                ->helperText('Only applies to a hybrid/flexi-structured loan product: length of this lender\'s interest-only initial stage. Leave blank to use the product\'s default.'),
            TextInput::make('interest_rate_from')->numeric()->suffix('%'),
            TextInput::make('interest_rate_to')->numeric()->suffix('%'),
            Fieldset::make('Processing fee')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('processing_fee_flat_amount_min')
                        ->label('Flat fee from (₹)')
                        ->helperText('Fill this OR the percentage fields below, not both — flat fee takes priority if both are set.')
                        ->numeric()
                        ->prefix('₹'),
                    TextInput::make('processing_fee_flat_amount_max')
                        ->label('Flat fee up to (₹)')
                        ->numeric()
                        ->prefix('₹'),
                    TextInput::make('processing_fee_percent_min')
                        ->label('Percentage from (%)')
                        ->numeric()
                        ->suffix('%'),
                    TextInput::make('processing_fee_percent_max')
                        ->label('Percentage up to (%)')
                        ->numeric()
                        ->suffix('%'),
                    Toggle::make('processing_fee_gst_extra')
                        ->label('GST is extra (not included above)')
                        ->default(true)
                        ->columnSpanFull(),
                    TextInput::make('processing_fee_note')
                        ->label('Note')
                        ->helperText('Use this for slab specifics, e.g. "1% up to ₹5L, 2% above ₹5L".')
                        ->columnSpanFull(),
                ]),
            Fieldset::make('Who typically qualifies')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('min_age')->numeric()->suffix('yrs'),
                    TextInput::make('max_age')->numeric()->suffix('yrs'),
                    TextInput::make('min_credit_score')->numeric(),
                    TextInput::make('min_monthly_income')->numeric()->prefix('₹')->suffix('/mo'),
                    TextInput::make('min_employment_vintage_months')->numeric()->suffix('months'),
                    CheckboxList::make('employment_types')
                        ->options(EmploymentType::class)
                        ->columns(2)
                        ->columnSpanFull()
                        ->helperText('Informational only — shown to visitors as "who qualifies" bullet points. Does not affect the eligibility rule engine used during the application journey.'),
                ]),
        ];
    }
}
