<?php

namespace App\Filament\Resources\LoanLandingPageContent\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Deliberately a DIFFERENT, smaller schema than LoanLandingPageForm — not a
 * variant of it — so there is never a copy-paste path for a business/routing
 * field to end up here. group, slug, loan_product_id, amount and sort_order
 * are intentionally absent: group/slug/loan_product_id/amount determine how
 * and where the page fits into the site's structure and menus, and
 * sort_order controls its position within them — none of that is website
 * "content" in the sense this resource exists for. See
 * LoanLandingPageContentResource for the matching authorization boundary and
 * RestrictsUpdateToAllowedFields for the server-side enforcement.
 */
class LoanLandingPageContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Landing page')
                    ->description('Read-only — identifies this page. Its loan product, group, URL slug and amount are structural/routing details managed by an administrator.')
                    ->columns(2)
                    ->components([
                        Placeholder::make('loan_product_reference')
                            ->label('Loan product')
                            ->content(fn ($record) => $record->loanProduct?->name ?? '—'),
                        Placeholder::make('slug_reference')
                            ->label('URL slug')
                            ->content(fn ($record) => $record->slug),
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
                            ->helperText('Optional. The page stops appearing publicly after this time.'),
                    ]),

                Section::make('Marketing')
                    ->description('Presentation only — none of these fields affect which loan product this page belongs to, its URL, or the loan journey.')
                    ->components([
                        TextInput::make('title')
                            ->label('Headline')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('excerpt')
                            ->label('Summary')
                            ->maxLength(160)
                            ->helperText('Shown under the headline and used as the fallback meta description.')
                            ->columnSpanFull(),
                        TextInput::make('cta_label')
                            ->label('Primary button label')
                            ->maxLength(255)
                            ->helperText('Overrides the default "Check Your Eligibility" button text. The button still always starts the real eligibility/loan journey — this only changes its label.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Content')
                    ->components([
                        RichEditor::make('body')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
