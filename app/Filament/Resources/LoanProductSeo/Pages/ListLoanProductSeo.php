<?php

namespace App\Filament\Resources\LoanProductSeo\Pages;

use App\Filament\Resources\LoanProductSeo\LoanProductSeoResource;
use Filament\Resources\Pages\ListRecords;

class ListLoanProductSeo extends ListRecords
{
    protected static string $resource = LoanProductSeoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
