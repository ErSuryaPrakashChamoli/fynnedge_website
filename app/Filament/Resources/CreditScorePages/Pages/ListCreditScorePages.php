<?php

namespace App\Filament\Resources\CreditScorePages\Pages;

use App\Filament\Resources\CreditScorePages\CreditScorePageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCreditScorePages extends ListRecords
{
    protected static string $resource = CreditScorePageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
