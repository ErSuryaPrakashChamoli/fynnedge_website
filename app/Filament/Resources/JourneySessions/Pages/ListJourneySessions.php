<?php

namespace App\Filament\Resources\JourneySessions\Pages;

use App\Filament\Resources\JourneySessions\JourneySessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJourneySessions extends ListRecords
{
    protected static string $resource = JourneySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
