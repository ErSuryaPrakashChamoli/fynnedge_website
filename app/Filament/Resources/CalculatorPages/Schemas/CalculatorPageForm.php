<?php

namespace App\Filament\Resources\CalculatorPages\Schemas;

use App\Models\CalculatorPage;
use App\Support\Calculators\CalculatorPageKey;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CalculatorPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('calculator_key')
                    ->label('Calculator')
                    ->options(CalculatorPageKey::class)
                    ->required()
                    ->unique(CalculatorPage::class, 'calculator_key', ignoreRecord: true)
                    ->helperText('Which calculator page this content appears on. Loan-category calculators (EMI, Eligibility, Prepayment) instead use the "Calculator explanation" field on the relevant Loan Product.'),
                TextInput::make('title')
                    ->helperText('Optional — leave blank to use the default section heading ("About this calculator").'),
                RichEditor::make('body')
                    ->label('Content')
                    ->helperText('Shown below the calculator, above the page footer. Written by the marketing team — edit anytime, changes go live immediately.')
                    ->columnSpanFull(),
            ]);
    }
}
