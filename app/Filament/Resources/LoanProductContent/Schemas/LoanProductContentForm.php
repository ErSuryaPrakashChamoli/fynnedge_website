<?php

namespace App\Filament\Resources\LoanProductContent\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Deliberately a DIFFERENT, smaller schema than LoanProductForm — not a
 * variant of it — so there is never a copy-paste path for a business field
 * to end up here. See LoanProductContentResource for the matching
 * authorization boundary and RestrictsUpdateToAllowedFields for the
 * server-side enforcement that backs this up.
 */
class LoanProductContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Loan product')
                    ->description('Read-only — identifies which product this content belongs to. Its name, slug, category and business/calculator configuration are managed by an administrator.')
                    ->components([
                        TextInput::make('name')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Publishing')
                    ->columns(2)
                    ->components([
                        Select::make('status')
                            ->options(PublishStatus::class)
                            ->default(PublishStatus::Draft)
                            ->required()
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->helperText('Leave blank to publish immediately once status is Published.')
                            ->visible(fn (callable $get) => $get('status') === PublishStatus::Published->value),
                        DateTimePicker::make('expires_at')
                            ->helperText('Optional. The product stops appearing publicly after this time.'),
                    ]),

                Section::make('Marketing')
                    ->description('Presentation only — none of these fields affect eligibility, interest, or the calculator.')
                    ->components([
                        TextInput::make('marketing_headline')
                            ->label('Marketing headline')
                            ->maxLength(255)
                            ->helperText('An optional promotional tagline shown above the product name, e.g. "India\'s fastest personal loan approval".')
                            ->columnSpanFull(),
                        TextInput::make('summary')
                            ->maxLength(160)
                            ->helperText('Shown on product cards and used as the fallback meta description.')
                            ->columnSpanFull(),
                        TagsInput::make('benefits')
                            ->helperText('Customer-facing benefits, distinct from "Key features" below, e.g. "Same-day disbursal".'),
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
                            ->label('Eligibility explanation')
                            ->helperText('Plain-language, customer-facing eligibility summary — not the lender rule engine, which is managed separately and unaffected by this field.'),
                        TagsInput::make('documents_required')
                            ->label('Documents explanation'),
                        TagsInput::make('process_steps'),
                    ]),
            ]);
    }
}
