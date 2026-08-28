<?php

namespace App\Filament\Resources\DocumentRequirements\Pages;

use App\Filament\Resources\DocumentRequirements\DocumentRequirementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentRequirements extends ListRecords
{
    protected static string $resource = DocumentRequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
