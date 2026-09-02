<?php

namespace App\Filament\Resources\LenderProducts\Pages;

use App\Filament\Imports\LenderProductImporter;
use App\Filament\Resources\LenderProducts\LenderProductResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListLenderProducts extends ListRecords
{
    protected static string $resource = LenderProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(LenderProductImporter::class),
        ];
    }
}
