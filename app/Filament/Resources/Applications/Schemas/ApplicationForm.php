<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Models\LenderProduct;
use App\Modules\Applications\Enums\ApplicationStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Application')
                    ->columns(2)
                    ->components([
                        Select::make('customer_id')
                            ->relationship('customer', 'email')
                            ->disabled(),
                        Select::make('lender_product_id')
                            ->label('Lender product')
                            ->relationship('lenderProduct', 'id')
                            ->getOptionLabelFromRecordUsing(
                                fn (LenderProduct $record) => "{$record->lender->name} — {$record->loanProduct->name}",
                            )
                            ->disabled(),
                        DateTimePicker::make('submitted_at')
                            ->disabled(),
                    ]),

                Section::make('Status')
                    ->columns(1)
                    ->components([
                        Select::make('status')
                            ->options(ApplicationStatus::class)
                            ->required()
                            ->helperText('Draft through Submitted are set by the website journey itself. Later stages happen in the lender\'s own processing and, until a FYNN-ON sync exists, can be recorded here manually as a stopgap.'),
                    ]),
            ]);
    }
}
