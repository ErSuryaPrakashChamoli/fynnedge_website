<?php

namespace App\Filament\Resources\PageSeos\Pages;

use App\Filament\Resources\PageSeos\PageSeoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPageSeos extends ListRecords
{
    protected static string $resource = PageSeoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
