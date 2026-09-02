<?php

namespace App\Filament\Resources\NavigationLinks\Pages;

use App\Filament\Resources\NavigationLinks\NavigationLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNavigationLink extends EditRecord
{
    protected static string $resource = NavigationLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
