<?php

namespace App\Filament\Resources\LoanProductContent\Pages;

use App\Filament\Resources\LoanProductContent\LoanProductContentResource;
use Filament\Resources\Pages\ListRecords;

class ListLoanProductContent extends ListRecords
{
    protected static string $resource = LoanProductContentResource::class;

    protected function getHeaderActions(): array
    {
        // No CreateAction — Marketing edits existing products' content only;
        // creating a new loan product is a business decision made elsewhere.
        return [];
    }
}
