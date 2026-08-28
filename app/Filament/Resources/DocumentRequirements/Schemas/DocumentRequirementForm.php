<?php

namespace App\Filament\Resources\DocumentRequirements\Schemas;

use App\Models\LenderProduct;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentRequirementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('lender_product_id')
                    ->label('Lender product')
                    ->relationship('lenderProduct', 'id')
                    ->getOptionLabelFromRecordUsing(
                        fn (LenderProduct $record) => "{$record->lender->name} — {$record->loanProduct->name}",
                    )
                    ->searchable()
                    ->required(),
                Select::make('document_type_id')
                    ->label('Document type')
                    ->relationship('documentType', 'label')
                    ->searchable()
                    ->required(),
                Toggle::make('is_required')
                    ->label('Required')
                    ->default(true),
                TextInput::make('order')
                    ->numeric()
                    ->default(0),
                TextInput::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
