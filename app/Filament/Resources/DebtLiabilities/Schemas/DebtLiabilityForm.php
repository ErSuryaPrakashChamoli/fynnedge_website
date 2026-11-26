<?php

namespace App\Filament\Resources\DebtLiabilities\Schemas;

use App\Models\Lender;
use App\Modules\DebtConsolidation\Enums\ConsolidationMode;
use App\Modules\DebtConsolidation\Enums\LiabilityAccountStatus;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use App\Modules\DebtConsolidation\Enums\RepaymentStructure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Staff entry and correction of an existing liability. Every change is kept
 * in the audit history (data-correction trail); figures are entered by hand
 * from the lender's statement — nothing is extracted from documents.
 */
class DebtLiabilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Loan')->columns(3)->columnSpanFull()->components([
                Select::make('liability_type')->options(LiabilityType::class)->required()->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $type = $state instanceof LiabilityType ? $state : LiabilityType::tryFrom((string) $state);

                        if ($type !== null) {
                            $set('repayment_structure', $type->defaultRepaymentStructure()->value);
                            $set('is_secured', $type->isSecuredByDefault());
                        }
                    }),
                Select::make('lender_id')->label('Lender (catalogue)')->options(fn () => Lender::query()->orderBy('name')->pluck('name', 'id'))->searchable()
                    ->afterStateUpdated(fn ($state, callable $set) => $state ? $set('lender_name', Lender::query()->find($state)?->getRawOriginal('name')) : null)->live(),
                TextInput::make('lender_name')->label('Lender name')->required()->maxLength(150),
                TextInput::make('account_reference')->label('Loan / card number')->maxLength(40)->helperText('Stored encrypted; lists show only the last 4 characters.'),
                Select::make('repayment_structure')->options(RepaymentStructure::class)->required(),
                Toggle::make('is_secured')->label('Secured')->inline(false),
            ]),
            Section::make('Figures')->columns(3)->columnSpanFull()->components([
                TextInput::make('outstanding_principal')->numeric()->prefix('₹')->required()->minValue(0.01),
                TextInput::make('interest_rate')->label('Rate (% a year)')->numeric()->minValue(0)->maxValue(60)->required()->default(0),
                Select::make('rate_basis')->options(['reducing' => 'Reducing balance', 'flat' => 'Flat', 'unknown' => 'Not known'])->default('reducing'),
                TextInput::make('monthly_payment')->label('EMI / monthly payment')->numeric()->prefix('₹')->minValue(0),
                TextInput::make('remaining_months')->numeric()->minValue(1)->maxValue(480),
                DatePicker::make('lock_in_ends_on')->label('Foreclosure lock-in ends'),
            ]),
            Section::make('Standing and closure costs')->columns(3)->columnSpanFull()->components([
                Select::make('account_status')->options(LiabilityAccountStatus::class)->default(LiabilityAccountStatus::Regular->value)->required(),
                TextInput::make('days_past_due')->numeric()->minValue(0)->default(0),
                TextInput::make('overdue_amount')->numeric()->prefix('₹')->minValue(0)->default(0),
                TextInput::make('pending_instalment')->label('Instalment due before closure')->numeric()->prefix('₹')->minValue(0)->default(0),
                TextInput::make('accrued_interest')->label('Accrued (broken-period) interest')->numeric()->prefix('₹')->minValue(0)->default(0),
                TextInput::make('foreclosure_charge_percent')->label('Foreclosure charge %')->numeric()->minValue(0)->maxValue(10)->default(0),
                TextInput::make('foreclosure_charge_fixed')->label('Fixed foreclosure fee')->numeric()->prefix('₹')->minValue(0)->default(0),
                TextInput::make('other_charges')->numeric()->prefix('₹')->minValue(0)->default(0),
            ]),
            Section::make('Consolidation')->columns(3)->columnSpanFull()->components([
                Select::make('consolidation_mode')->options(ConsolidationMode::class)->default(ConsolidationMode::Full->value)->required()->live(),
                TextInput::make('partial_amount')->label('Amount to pay down')->numeric()->prefix('₹')->minValue(1)
                    ->visible(fn (Get $get): bool => $get->enum('consolidation_mode', ConsolidationMode::class, isNullable: true) === ConsolidationMode::Partial)
                    ->required(fn (Get $get): bool => $get->enum('consolidation_mode', ConsolidationMode::class, isNullable: true) === ConsolidationMode::Partial),
                Textarea::make('notes')->columnSpanFull()->maxLength(2000),
            ]),
        ]);
    }
}
