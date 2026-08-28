<?php

namespace App\Filament\Resources\JourneyDefinitions\Pages;

use App\Filament\Resources\JourneyDefinitions\JourneyDefinitionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJourneyDefinition extends EditRecord
{
    protected static string $resource = JourneyDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
