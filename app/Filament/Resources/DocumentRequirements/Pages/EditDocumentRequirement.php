<?php

namespace App\Filament\Resources\DocumentRequirements\Pages;

use App\Filament\Resources\DocumentRequirements\DocumentRequirementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentRequirement extends EditRecord
{
    protected static string $resource = DocumentRequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
