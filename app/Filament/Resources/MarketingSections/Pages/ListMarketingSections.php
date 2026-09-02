<?php

namespace App\Filament\Resources\MarketingSections\Pages;

use App\Filament\Resources\MarketingSections\MarketingSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingSections extends ListRecords
{
    protected static string $resource = MarketingSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
