<?php

namespace App\Filament\Resources\LoanProducts\Schemas;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Filament\Schemas\SeoFormSection;
use App\Models\LoanProduct;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
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
                        DateTimePicker::make('expires_at')
                            ->helperText('Optional. The product stops appearing publicly after this time.'),
                    ]),

                Section::make('Marketing')
                    ->description('Presentation only — none of these fields affect eligibility, interest, or the calculator below.')
                    ->components([
                        TextInput::make('marketing_headline')
                            ->label('Marketing headline')
                            ->maxLength(255)
                            ->helperText('An optional promotional tagline shown above the product name, e.g. "India\'s fastest personal loan approval".')
                            ->columnSpanFull(),
                        TagsInput::make('benefits')
                            ->helperText('Customer-facing benefits, distinct from the "Key features" list below, e.g. "Same-day disbursal".'),
                        TextInput::make('cta_label')
                            ->label('Primary button label')
                            ->maxLength(255)
                            ->helperText('Overrides the default "Check Your Eligibility" button text. The button still always starts the real eligibility/loan journey — this only changes its label.'),
                        FileUpload::make('image_path')
                            ->label('Product image')
                            ->image()
                            ->disk('public')
                            ->directory('loan-products')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(5120)
                            ->helperText('Optional. JPG or PNG, up to 5MB.'),
                        TextInput::make('image_alt')
                            ->label('Image alt text')
                            ->maxLength(255),
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

                Section::make('EMI calculator')
                    ->description('Drives the public EMI calculator for this product directly — its slider ranges, validation limits and starting values all come from here. Leave any field blank and the calculator won\'t show for this product until it\'s filled in.')
                    ->columns(3)
                    ->components([
                        TextInput::make('min_amount')
                            ->label('Minimum amount')
                            ->numeric()
                            ->prefix('₹'),
                        TextInput::make('max_amount')
                            ->label('Maximum amount')
                            ->numeric()
                            ->prefix('₹'),
                        TextInput::make('default_amount')
                            ->label('Default amount')
                            ->numeric()
                            ->prefix('₹'),
                        TextInput::make('min_tenure_months')
                            ->label('Minimum tenure')
                            ->numeric()
                            ->suffix('months'),
                        TextInput::make('max_tenure_months')
                            ->label('Maximum tenure')
                            ->numeric()
                            ->suffix('months'),
                        TextInput::make('default_tenure_months')
                            ->label('Default tenure')
                            ->numeric()
                            ->suffix('months'),
                        TextInput::make('default_initial_tenure_months')
                            ->label('Default initial tenure')
                            ->numeric()
                            ->suffix('months')
                            ->helperText('Hybrid/flexi products only: default length of the interest-only initial stage, before it converts to principal + interest. A lender can override this on its own offer. Ignored for a standard EMI product.'),
                        TextInput::make('min_interest_rate')
                            ->label('Minimum rate (slider/validation)')
                            ->numeric()
                            ->suffix('% p.a.'),
                        TextInput::make('max_interest_rate')
                            ->label('Maximum rate (slider/validation)')
                            ->numeric()
                            ->suffix('% p.a.')
                            ->helperText('Set above the quoted ceiling for a "24%+"-style range, so the slider can actually reach higher rates.'),
                        TextInput::make('default_interest_rate')
                            ->label('Default rate')
                            ->numeric()
                            ->suffix('% p.a.'),
                        TextInput::make('interest_rate_note')
                            ->label('Displayed rate range')
                            ->helperText('The indicative range shown to visitors, e.g. "10.49% – 24%+". Purely text — does not affect the slider.')
                            ->columnSpanFull(),
                        RichEditor::make('calculator_explanation')
                            ->label('EMI calculation explanation')
                            ->helperText('Shown on the standalone EMI calculator page for this loan type — separate from the "Content" body above, so it can be edited independently.')
                            ->columnSpanFull(),
                    ]),

                SeoFormSection::make(),
            ]);
    }
}
