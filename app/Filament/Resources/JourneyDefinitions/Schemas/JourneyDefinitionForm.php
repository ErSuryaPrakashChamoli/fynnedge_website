<?php

namespace App\Filament\Resources\JourneyDefinitions\Schemas;

use App\Modules\Journey\Enums\JourneyDefinitionStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JourneyDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('loan_product_id')
                    ->relationship('loanProduct', 'name')
                    ->required()
                    ->searchable(),
                TextInput::make('version')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('status')
                    ->options(JourneyDefinitionStatus::class)
                    ->default(JourneyDefinitionStatus::Draft)
                    ->required(),
            ]);
    }
}
