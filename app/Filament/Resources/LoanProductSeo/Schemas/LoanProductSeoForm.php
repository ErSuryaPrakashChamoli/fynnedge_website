<?php

namespace App\Filament\Resources\LoanProductSeo\Schemas;

use App\Filament\Schemas\SeoFormSection;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Exposes only the existing, shared SeoFormSection — the same component the
 * full LoanProductResource, ArticleResource, PageResource and
 * LoanLandingPageResource already use. No LoanProduct-level fields are
 * defined here at all, so there is nothing at that level for a crafted
 * request to tamper with (see EditLoanProductSeo's allowlist, which is
 * empty for exactly this reason).
 */
class LoanProductSeoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Loan product')
                    ->description('Read-only — identifies which product this SEO metadata belongs to. Its content, name, slug and business configuration are managed elsewhere.')
                    ->components([
                        TextInput::make('name')
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                SeoFormSection::make(),
            ]);
    }
}
