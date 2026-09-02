<?php

namespace App\Filament\Resources\LoanLandingPageContent\Pages;

use App\Filament\Resources\LoanLandingPageContent\LoanLandingPageContentResource;
use Filament\Resources\Pages\ListRecords;

class ListLoanLandingPageContent extends ListRecords
{
    protected static string $resource = LoanLandingPageContentResource::class;

    protected function getHeaderActions(): array
    {
        // No CreateAction — Marketing edits existing landing pages' content
        // only; creating/removing/structuring pages is a business decision
        // made elsewhere via the full LoanLandingPageResource.
        return [];
    }
}
