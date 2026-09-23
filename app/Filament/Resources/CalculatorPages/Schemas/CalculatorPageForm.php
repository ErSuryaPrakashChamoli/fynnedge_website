<?php

namespace App\Filament\Resources\CalculatorPages\Schemas;

use App\Models\CalculatorPage;
use App\Support\Calculators\CalculatorCatalog;
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
                    ->options(CalculatorCatalog::pages())
                    ->searchable()
                    ->required()
                    ->unique(CalculatorPage::class, 'calculator_key', ignoreRecord: true)
                    ->helperText('Which calculator page this content appears on. Each calculator has its own content — e.g. the Home Loan EMI and Home Loan Eligibility calculators are edited separately.'),
                TextInput::make('title')
                    ->label('Heading')
                    ->helperText('Optional — leave blank to use the default heading, e.g. "About the Home Loan EMI Calculator".'),
                RichEditor::make('body')
                    ->label('About Calculator')
                    ->helperText('Shown below the calculator. Leave empty to hide the section — loan calculators then fall back to the Loan Product\'s "EMI calculation explanation". Changes go live immediately.')
                    ->columnSpanFull(),
            ]);
    }
}
