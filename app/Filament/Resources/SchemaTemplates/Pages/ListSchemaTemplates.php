<?php

namespace App\Filament\Resources\SchemaTemplates\Pages;

use App\Filament\Resources\SchemaTemplates\SchemaTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSchemaTemplates extends ListRecords
{
    protected static string $resource = SchemaTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
