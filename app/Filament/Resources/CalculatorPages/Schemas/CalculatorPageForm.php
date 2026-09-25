<?php

namespace App\Filament\Resources\CalculatorPages\Schemas;

use App\Models\CalculatorPage;
use App\Support\Calculators\CalculatorCatalog;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                    ->disabledOn('edit')
                    ->helperText('Which calculator page this content appears on. Each calculator has its own content — e.g. the Home Loan EMI and Home Loan Eligibility calculators are edited separately. It cannot be changed after saving: to write content for another calculator, create a new entry.')
                    ->columnSpanFull(),
                Section::make('Page heading')
                    ->description('The headline and introduction at the top of this page only. Leave a field blank to use the shared wording from Website Settings → Calculators Page.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('heading')
                            ->label('Headline')
                            ->maxLength(100),
                        TextInput::make('meta_title')
                            ->label('Page title (browser tab & search results)')
                            ->maxLength(70),
                        Textarea::make('description')
                            ->label('Introduction')
                            ->rows(2)
                            ->maxLength(300),
                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->rows(2)
                            ->maxLength(160),
                    ]),
                TextInput::make('title')
                    ->label('"About" section heading')
                    ->columnSpanFull()
                    ->helperText('Optional — leave blank to use the default heading, e.g. "About the Home Loan EMI Calculator".'),
                RichEditor::make('body')
                    ->label('About Calculator')
                    ->helperText('Shown below the calculator. Leave empty to hide the section — loan calculators then fall back to the Loan Product\'s "EMI calculation explanation". Changes go live immediately.')
                    ->columnSpanFull(),
            ]);
    }
}
