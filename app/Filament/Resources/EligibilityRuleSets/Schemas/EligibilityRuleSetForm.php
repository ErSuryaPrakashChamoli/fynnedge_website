<?php

namespace App\Filament\Resources\EligibilityRuleSets\Schemas;

use App\Models\LenderProduct;
use App\Modules\Eligibility\Enums\EligibilityRuleSetStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EligibilityRuleSetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('lender_product_id')
                    ->label('Lender product')
                    ->relationship('lenderProduct', 'id')
                    ->getOptionLabelFromRecordUsing(
                        fn (LenderProduct $record) => "{$record->lender->name} — {$record->loanProduct->name}",
                    )
                    ->searchable()
                    ->required()
                    ->columnSpan(2),
                TextInput::make('version')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('status')
                    ->options(EligibilityRuleSetStatus::class)
                    ->default(EligibilityRuleSetStatus::Draft)
                    ->required(),
                DatePicker::make('effective_from'),
                DatePicker::make('effective_until'),
                Textarea::make('notes')
                    ->helperText('Why this version exists — e.g. "FOIR relaxed from 50% to 55% per bank circular dated ..."')
                    ->columnSpanFull(),
            ]);
    }
}
