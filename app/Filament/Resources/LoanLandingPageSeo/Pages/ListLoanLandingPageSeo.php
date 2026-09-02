<?php

namespace App\Filament\Resources\LoanLandingPageSeo\Pages;

use App\Filament\Resources\LoanLandingPageSeo\LoanLandingPageSeoResource;
use Filament\Resources\Pages\ListRecords;

class ListLoanLandingPageSeo extends ListRecords
{
    protected static string $resource = LoanLandingPageSeoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
