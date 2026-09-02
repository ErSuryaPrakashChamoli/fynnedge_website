<?php

namespace App\Filament\Resources\LoanLandingPages\Pages;

use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLoanLandingPages extends ListRecords
{
    protected static string $resource = LoanLandingPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
