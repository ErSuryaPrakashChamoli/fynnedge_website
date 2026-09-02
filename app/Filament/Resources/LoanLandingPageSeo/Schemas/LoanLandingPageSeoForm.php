<?php

namespace App\Filament\Resources\LoanLandingPageSeo\Schemas;

use App\Filament\Schemas\SeoFormSection;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Exposes only the existing, shared SeoFormSection — the same component the
 * full LoanLandingPageResource, LoanProductSeoResource, ArticleResource and
 * PageResource already use. No LoanLandingPage-level fields are defined here
 * at all, so there is nothing at that level for a crafted request to tamper
 * with (see EditLoanLandingPageSeo's allowlist, which is empty for exactly
 * this reason).
 */
class LoanLandingPageSeoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Landing page')
                    ->description('Read-only — identifies which page this SEO metadata belongs to. Its content, loan product, group, slug and business configuration are managed elsewhere.')
                    ->columns(2)
                    ->components([
                        Placeholder::make('title_reference')
                            ->label('Page title')
                            ->content(fn ($record) => $record->title),
                        Placeholder::make('loan_product_reference')
                            ->label('Loan product')
                            ->content(fn ($record) => $record->loanProduct?->name ?? '—'),
                    ]),

                SeoFormSection::make(),
            ]);
    }
}
