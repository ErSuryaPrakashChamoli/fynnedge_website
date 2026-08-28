<?php

namespace App\Filament\Resources\LoanProducts\Schemas;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Filament\Schemas\SeoFormSection;
use App\Models\LoanProduct;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class LoanProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->required()
                            ->unique(LoanProduct::class, 'slug', ignoreRecord: true),
                        Select::make('category')
                            ->options(LoanCategory::class)
                            ->required(),
                        Select::make('calculator_key')
                            ->options([
                                'emi' => 'EMI',
                                'personal-loan' => 'Personal Loan',
                                'home-loan' => 'Home Loan',
                                'business-loan' => 'Business Loan',
                                'lap' => 'Loan Against Property',
                            ]),
                        TextInput::make('summary')
                            ->maxLength(160)
                            ->helperText('Shown on product cards and used as the fallback meta description.')
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(PublishStatus::class)
                            ->default(PublishStatus::Draft)
                            ->required()
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->visible(fn (callable $get) => $get('status') === PublishStatus::Published->value),
                    ]),

                Section::make('Content')
                    ->components([
                        RichEditor::make('body')
                            ->columnSpanFull(),
                        TagsInput::make('features')
                            ->helperText('Short benefit bullets, e.g. "No collateral required".'),
                        TagsInput::make('eligibility_points')
                            ->helperText('Plain-language eligibility summary, not the lender rule engine.'),
                        TagsInput::make('documents_required'),
                        TagsInput::make('process_steps'),
                    ]),

                SeoFormSection::make(),
            ]);
    }
}
