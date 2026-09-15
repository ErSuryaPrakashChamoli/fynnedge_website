<?php

namespace App\Filament\Resources\PromoBars\Pages;

use App\Filament\Resources\PromoBars\PromoBarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromoBars extends ListRecords
{
    protected static string $resource = PromoBarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
