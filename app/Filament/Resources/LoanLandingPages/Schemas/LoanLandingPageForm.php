<?php

namespace App\Filament\Resources\LoanLandingPages\Schemas;

use App\Enums\LandingPageGroup;
use App\Enums\PublishStatus;
use App\Filament\Schemas\SeoFormSection;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class LoanLandingPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->columns(2)
                    ->components([
                        Select::make('loan_product_id')
                            ->label('Loan product')
                            ->options(fn () => LoanProduct::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('group')
                            ->options(LandingPageGroup::class)
                            ->required()
                            ->live(),
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->required()
                            ->unique(LoanLandingPage::class, 'slug', ignoreRecord: true),
                        TextInput::make('amount')
                            ->label('Amount (₹)')
                            ->numeric()
                            ->visible(fn (callable $get) => $get('group') === LandingPageGroup::ByAmount->value),
                        TextInput::make('excerpt')
                            ->maxLength(160)
                            ->columnSpanFull(),
                        TextInput::make('cta_label')
                            ->label('Primary button label')
                            ->helperText('Overrides the default "Check Your Eligibility" button text. The button still always starts the real eligibility/loan journey — this only changes its label.')
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(PublishStatus::class)
                            ->default(PublishStatus::Draft)
                            ->required()
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->visible(fn (callable $get) => $get('status') === PublishStatus::Published->value),
                        DateTimePicker::make('expires_at')
                            ->helperText('Optional. The page stops appearing publicly after this time.'),
                    ]),

                RichEditor::make('body'),

                SeoFormSection::make(),
            ]);
    }
}
