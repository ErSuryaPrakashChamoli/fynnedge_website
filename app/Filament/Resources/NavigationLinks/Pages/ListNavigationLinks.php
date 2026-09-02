<?php

namespace App\Filament\Resources\NavigationLinks\Pages;

use App\Filament\Resources\NavigationLinks\NavigationLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNavigationLinks extends ListRecords
{
    protected static string $resource = NavigationLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
