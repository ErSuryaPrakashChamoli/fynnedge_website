<?php

namespace App\Filament\Resources\JourneyDefinitions\Pages;

use App\Filament\Resources\JourneyDefinitions\JourneyDefinitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJourneyDefinitions extends ListRecords
{
    protected static string $resource = JourneyDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
