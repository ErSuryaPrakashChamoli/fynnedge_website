<?php

namespace App\Filament\Resources\DebtLenderPolicies\Schemas;

use App\Filament\Resources\DebtApplications\Pages\ViewDebtApplication;
use App\Models\Lender;
use App\Modules\Applications\Models\DocumentType;
use App\Modules\DebtConsolidation\Enums\ApplicantType;
use App\Modules\DebtConsolidation\Enums\CollateralType;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Enums\LiabilityType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * A lender offer's debt consolidation criteria, entered from the lender's
 * own policy document or communication — which the Source section records.
 * Empty criteria are not checked; nothing is assumed on a lender's behalf.
 */
class DebtLenderPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Offer')->columns(3)->components([
                Select::make('lender_product_id')->label('Lender offer')->options(fn () => ViewDebtApplication::lenderProductOptions())->searchable()->required(),
                Select::make('financing_route')->options(FinancingRoute::class)->required(),
                TextInput::make('version')->numeric()->required()->minValue(1)->default(1),
                CheckboxList::make('applicant_types')->options(ApplicantType::class)->required()->columns(2),
            ]),
            Section::make('Debts it takes over')->components([
                CheckboxList::make('accepted_liability_types')->options(LiabilityType::class)->required()->columns(3)->bulkToggleable(),
                Select::make('excluded_existing_lender_ids')->label('Will not take over loans from')->multiple()->options(fn () => Lender::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
                Toggle::make('accepts_own_loans')->label('Takes over its own existing loans')->default(true),
                TextInput::make('max_days_past_due')->label('Maximum days past due on any loan')->numeric()->minValue(0),
            ]),
            Section::make('Amount, rate and tenure')->columns(3)->components([
                TextInput::make('min_loan_amount')->numeric()->prefix('₹')->helperText('Blank = the offer\'s minimum.'),
                TextInput::make('max_loan_amount')->numeric()->prefix('₹')->helperText('Blank = the offer\'s maximum.'),
                TextInput::make('processing_fee_percent')->label('Processing fee %')->numeric(),
                TextInput::make('interest_rate_min')->label('Rate from %')->numeric(),
                TextInput::make('interest_rate_max')->label('Rate up to %')->numeric(),
                TextInput::make('min_tenure_months')->numeric(),
                TextInput::make('max_tenure_months')->numeric(),
            ]),
            Section::make('Applicant criteria')->columns(3)->components([
                TextInput::make('min_monthly_income')->label('Minimum net monthly income')->numeric()->prefix('₹'),
                TextInput::make('max_foir_percent')->label('Maximum FOIR %')->numeric(),
                TextInput::make('min_credit_score')->numeric()->minValue(300)->maxValue(900),
                TextInput::make('min_business_vintage_months')->label('Minimum business vintage (months)')->numeric(),
                TextInput::make('min_annual_turnover')->numeric()->prefix('₹'),
                TextInput::make('min_dscr')->label('Minimum DSCR')->numeric(),
            ]),
            Section::make('Security')->columns(3)->components([
                Toggle::make('requires_collateral')->label('Needs collateral'),
                CheckboxList::make('accepted_collateral_types')->options(CollateralType::class)->columns(2),
                TextInput::make('max_ltv_percent')->label('Maximum loan-to-value %')->numeric(),
            ]),
            Section::make('Documents and restrictions')->components([
                Select::make('required_document_type_ids')->label('Documents the lender needs')->multiple()->options(fn () => DocumentType::query()->orderBy('order')->pluck('label', 'id')),
                Textarea::make('foreclosure_restrictions')->label('Foreclosure / takeover restrictions')->maxLength(2000),
            ]),
            Section::make('Source and validity')->columns(3)->components([
                TextInput::make('source_name')->label('Source')->required()->maxLength(150)->helperText('e.g. "HDFC Bank DSA product note PL-BT".'),
                TextInput::make('source_reference')->maxLength(150),
                TextInput::make('source_url')->url()->maxLength(255),
                DatePicker::make('source_received_on')->label('Received on')->required(),
                DatePicker::make('effective_from')->required(),
                DatePicker::make('effective_until'),
                Textarea::make('notes')->columnSpanFull()->maxLength(2000),
            ]),
        ]);
    }
}
